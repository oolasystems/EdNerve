<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAccountType;
use App\Http\Controllers\Controller;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\StudentFeeDue;
use App\Models\FeePayment;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use RequiresAccountType;

    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * List fee heads for the institution.
     */
    public function heads(Request $request)
    {
        $heads = FeeHead::where('institution_id', $request->user()->institution_id)
            ->orderBy('name')
            ->get(['id', 'institution_id', 'code', 'name', 'description', 'status']);

        return response()->json([
            'fee_heads' => $heads,
        ]);
    }

    /**
     * List fee structures for the institution (with programme/session/head included).
     */
    public function structures(Request $request)
    {
        $institutionId = $request->user()->institution_id;

        $query = FeeStructure::where('institution_id', $institutionId)
            ->with(['programme:id,code,name', 'academicSession:id,code,name', 'feeHead:id,code,name'])
            ->orderBy('programme_id')
            ->orderBy('fee_head_id');

        if ($programmeId = $request->query('programme_id')) {
            $query->where('programme_id', (int) $programmeId);
        }
        if ($sessionId = $request->query('academic_session_id')) {
            $query->where('academic_session_id', (int) $sessionId);
        }

        return response()->json([
            'structures' => $query->get(),
        ]);
    }

    public function storeStructure(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'programme_id' => ['nullable', 'integer'],
            'academic_session_id' => ['nullable', 'integer'],
            'fee_head_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:annual,semester,one_time'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verify ownership of referenced rows
        if (!empty($validated['programme_id'])) {
            \App\Models\Programme::where('institution_id', $institutionId)
                ->findOrFail($validated['programme_id']);
        }
        if (!empty($validated['academic_session_id'])) {
            \App\Models\AcademicSession::where('institution_id', $institutionId)
                ->findOrFail($validated['academic_session_id']);
        }
        FeeHead::where('institution_id', $institutionId)->findOrFail($validated['fee_head_id']);

        $structure = FeeStructure::create([
            'institution_id' => $institutionId,
            'programme_id' => $validated['programme_id'] ?? null,
            'academic_session_id' => $validated['academic_session_id'] ?? null,
            'fee_head_id' => $validated['fee_head_id'],
            'amount' => $validated['amount'],
            'frequency' => $validated['frequency'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'active',
        ]);

        $this->audit->record(
            $request,
            event: 'finance.structure_created',
            module: 'institution',
            subject: $structure->fresh(['programme', 'academicSession', 'feeHead']),
            newValues: $structure->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Fee structure created.',
            'structure' => $structure->fresh(['programme', 'academicSession', 'feeHead']),
        ], 201);
    }

    /**
     * Return the student's full ledger (dues + payments + summary).
     */
    public function ledger(Request $request, int $profileId)
    {
        $institutionId = $request->user()->institution_id;

        $profile = \App\Models\StudentProfile::where('institution_id', $institutionId)
            ->with('user:id,name,email')
            ->findOrFail($profileId);

        $dues = StudentFeeDue::where('institution_id', $institutionId)
            ->where('student_profile_id', $profileId)
            ->with(['feeHead:id,code,name', 'academicSession:id,code'])
            ->orderBy('due_date')
            ->get();

        $payments = FeePayment::where('institution_id', $institutionId)
            ->where('student_profile_id', $profileId)
            ->orderByDesc('paid_at')
            ->get();

        $totalDue     = $dues->sum('amount');
        $totalPaid    = $dues->sum('paid_amount');
        $balance      = $totalDue - $totalPaid;

        return response()->json([
            'student' => $profile,
            'summary' => [
                'total_due'      => (float) $totalDue,
                'total_paid'     => (float) $totalPaid,
                'balance'        => (float) $balance,
                'dues_count'     => $dues->count(),
                'payments_count' => $payments->count(),
            ],
            'dues' => $dues,
            'payments' => $payments,
        ]);
    }

    /**
     * Record a manual payment against a student's dues.
     * Allocates the amount to dues starting with the oldest pending/partial dues.
     */
    public function recordPayment(Request $request, int $profileId)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,cheque,demand_draft,upi,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $profile = \App\Models\StudentProfile::where('institution_id', $institutionId)
            ->findOrFail($profileId);

        $amount = (float) $validated['amount'];

        $receiptNumber = $this->generateReceiptNumber($institutionId);

        $payment = \DB::transaction(function () use ($institutionId, $profileId, $validated, $amount, $receiptNumber, $request) {
            // Load outstanding dues (oldest first, but prefer whole unpaid blocks)
            $dues = StudentFeeDue::where('institution_id', $institutionId)
                ->where('student_profile_id', $profileId)
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            $remaining = $amount;
            foreach ($dues as $due) {
                if ($remaining <= 0) break;
                $balance = (float) $due->amount - (float) $due->paid_amount;
                $apply = min($balance, $remaining);
                $due->paid_amount = (float) $due->paid_amount + $apply;
                $due->status = $due->paid_amount >= $due->amount ? 'paid' : 'partial';
                $due->save();
                $remaining -= $apply;
            }

            $payment = FeePayment::create([
                'institution_id' => $institutionId,
                'student_profile_id' => $profileId,
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'paid_at' => $validated['paid_at'] ?? now(),
                'received_by' => $request->user()->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            return $payment;
        });

        $this->audit->record(
            $request,
            event: 'finance.payment_received',
            module: 'institution',
            subject: $payment,
            newValues: $payment->toArray(),
            institutionId: $institutionId,
        );

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => $payment,
        ], 201);
    }

    /**
     * List recent receipts (institution admin only).
     */
    public function receipts(Request $request)
    {
        $this->ensureInstitutionAdmin($request);

        $institutionId = $request->user()->institution_id;

        $payments = FeePayment::where('institution_id', $institutionId)
            ->with(['studentProfile.user:id,name,email'])
            ->orderByDesc('paid_at')
            ->paginate((int) $request->query('per_page', 20) ?: 20)
            ->through(fn ($p) => $p);

        return response()->json($payments);
    }

    private function generateReceiptNumber(int $institutionId): string
    {
        $institution = \App\Models\Institution::findOrFail($institutionId);
        $year = now()->year;
        $count = FeePayment::where('institution_id', $institutionId)
            ->whereYear('paid_at', $year)
            ->lockForUpdate()
            ->count() + 1;
        return strtoupper($institution->code) . '-' . $year . '-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }
}
