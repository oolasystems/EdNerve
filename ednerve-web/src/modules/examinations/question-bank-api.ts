import apiClient from "../../shared/lib/api-client";

export type QuestionDifficulty = "easy" | "medium" | "hard";
export type QuestionType = "mcq" | "short" | "long" | "numerical";
export type QuestionStatus = "draft" | "active" | "archived";
export type PaperStatus = "draft" | "prepared" | "checked" | "approved" | "ratified" | "final" | "retired";
export type PaperSource = "manual" | "blueprint";

export interface BankQuestion {
  id: number;
  subject_id: number;
  subject?: { id: number; code: string; name: string } | null;
  unit: string | null;
  difficulty: QuestionDifficulty;
  type: QuestionType;
  marks: number;
  question_text: string;
  model_answer: string;
  tags: string | null;
  status: QuestionStatus;
  created_at: string;
}

export interface PaperSection {
  id: number;
  title: string;
  instruction: string | null;
  position: number;
  items: PaperItem[];
}

export interface PaperItem {
  id: number;
  position: number;
  marks: number;
  question_id: number;
  type: QuestionType | null;
  difficulty: QuestionDifficulty | null;
  unit: string | null;
  question_text: string | null;
  model_answer: string | null;
}

export interface PaperApproval {
  stage: "prepared" | "checked" | "approved" | "ratified";
  decision: "approved" | "rejected";
  actor_id: number;
  remarks: string | null;
  created_at: string;
}

export interface QuestionPaperDetail {
  id: number;
  code: string;
  status: PaperStatus;
  source: PaperSource;
  instructions: string | null;
  total_marks: number;
  template_id: number | null;
  numbering_scheme: string | null;
  prepared_by: number | null;
  prepared_at: string | null;
  checked_by: number | null;
  checked_at: string | null;
  approved_by: number | null;
  approved_at: string | null;
  ratified_by: number | null;
  ratified_at: string | null;
  approvals: PaperApproval[];
  subject?: { id: number; code: string; name: string } | null;
  sections: PaperSection[];
}

export interface QuestionPaperTemplate {
  id: number;
  institution_id: number;
  name: string;
  assessment_kind: "exam" | "unit_test" | "mid" | "sessional";
  total_marks: string | number;
  pattern: Array<{ title: string; position: number; instruction?: string | null; items: number; marks: number }> | null;
  instructions_md: string | null;
  status: "active" | "inactive";
  created_at?: string;
  updated_at?: string;
}

export interface QuestionPaperListItem {
  id: number;
  code: string;
  status: PaperStatus;
  source: PaperSource;
  instructions: string | null;
  total_marks: string;
  items_count: number;
  subject?: { id: number; code: string; name: string } | null;
  created_at: string;
}

export interface BlueprintSectionInput {
  title: string;
  count: number;
  marks: number;
  difficulty?: QuestionDifficulty;
}

export const questionBankService = {
  async getQuestions(params?: {
    subject_id?: number;
    unit?: string;
    difficulty?: string;
    type?: string;
    status?: string;
    q?: string;
  }): Promise<{ questions: BankQuestion[] }> {
    const response = await apiClient.get<{ questions: BankQuestion[] }>("/question-bank", { params });
    return response.data;
  },

  async createQuestion(payload: {
    subject_id: number;
    unit?: string;
    difficulty: QuestionDifficulty;
    type: QuestionType;
    marks: number;
    question_text: string;
    model_answer: string;
    tags?: string;
    status?: QuestionStatus;
  }) {
    const response = await apiClient.post("/question-bank", payload);
    return response.data;
  },

  async updateQuestion(id: number, payload: Partial<BankQuestion> & { marks?: number }) {
    const response = await apiClient.patch(`/question-bank/${id}`, payload);
    return response.data;
  },

  async archiveQuestion(id: number) {
    const response = await apiClient.delete(`/question-bank/${id}`);
    return response.data;
  },

  async getPapers(params?: { subject_id?: number; status?: string }): Promise<{ papers: QuestionPaperListItem[] }> {
    const response = await apiClient.get<{ papers: QuestionPaperListItem[] }>("/question-bank/papers", { params });
    return response.data;
  },

  async createPaper(payload: {
    subject_id: number;
    code: string;
    instructions?: string;
    sections: Array<{ title: string; instruction?: string }>;
  }) {
    const response = await apiClient.post("/question-bank/papers", payload);
    return response.data;
  },

  async generatePaper(payload: {
    subject_id: number;
    code?: string;
    instructions?: string;
    sections: BlueprintSectionInput[];
  }) {
    const response = await apiClient.post("/question-bank/papers/generate", payload);
    return response.data;
  },

  async getPaper(id: number): Promise<QuestionPaperDetail> {
    const response = await apiClient.get<QuestionPaperDetail>(`/question-bank/papers/${id}`);
    return response.data;
  },

  async updatePaper(id: number, payload: Partial<{ code: string; instructions: string; status: PaperStatus }>) {
    const response = await apiClient.patch(`/question-bank/papers/${id}`, payload);
    return response.data;
  },

  async deletePaper(id: number) {
    const response = await apiClient.delete(`/question-bank/papers/${id}`);
    return response.data;
  },

  async addPaperSection(paperId: number, payload: { title: string; instruction?: string }) {
    const response = await apiClient.post(`/question-bank/papers/${paperId}/sections`, payload);
    return response.data;
  },

  async addPaperItem(paperId: number, payload: { section_id: number; question_id: number; marks_override?: number }) {
    const response = await apiClient.post(`/question-bank/papers/${paperId}/items`, payload);
    return response.data;
  },

  async removePaperItem(paperId: number, itemId: number) {
    const response = await apiClient.delete(`/question-bank/papers/${paperId}/items/${itemId}`);
    return response.data;
  },

  async getPrintPaper(id: number, withAnswers = false): Promise<{ paper: QuestionPaperDetail; with_answers: boolean }> {
    const response = await apiClient.get(`/question-bank/papers/${id}/print`, {
      params: withAnswers ? { with_answers: 1 } : undefined,
    });
    return response.data;
  },

  // ---------- Paper templates (Phase B) ----------
  async getTemplates(): Promise<{ templates: QuestionPaperTemplate[] }> {
    const response = await apiClient.get<{ templates: QuestionPaperTemplate[] }>("/question-paper-templates");
    return response.data;
  },

  async createTemplate(payload: Partial<QuestionPaperTemplate>) {
    const response = await apiClient.post("/question-paper-templates", payload);
    return response.data;
  },

  async updateTemplate(id: number, payload: Partial<QuestionPaperTemplate>) {
    const response = await apiClient.patch(`/question-paper-templates/${id}`, payload);
    return response.data;
  },

  async deactivateTemplate(id: number) {
    const response = await apiClient.delete(`/question-paper-templates/${id}`);
    return response.data;
  },

  // ---------- Paper approval workflow (Phase B) ----------
  async submitPaper(id: number) {
    const response = await apiClient.post(`/question-bank/papers/${id}/submit`);
    return response.data;
  },

  async checkPaper(id: number, remarks?: string) {
    const response = await apiClient.post(`/question-bank/papers/${id}/check`, { remarks });
    return response.data;
  },

  async approvePaper(id: number, remarks?: string) {
    const response = await apiClient.post(`/question-bank/papers/${id}/approve`, { remarks });
    return response.data;
  },

  async ratifyPaper(id: number, remarks?: string) {
    const response = await apiClient.post(`/question-bank/papers/${id}/ratify`, { remarks });
    return response.data;
  },

  async rejectPaper(id: number, remarks: string) {
    const response = await apiClient.post(`/question-bank/papers/${id}/reject`, { remarks });
    return response.data;
  },
};
