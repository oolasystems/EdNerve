import { useParams, useSearchParams, Link } from "react-router-dom";
import { Printer, ArrowLeft, Eye, EyeOff } from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { questionBankService } from "../question-bank-api";
import "../../institution/institution.css";

import { Button, Spinner } from "../../../shared/ui";

const QuestionPaperPrintPage = () => {
  const { paperId } = useParams<{ paperId: string }>();
  const id = Number(paperId);
  const [searchParams, setSearchParams] = useSearchParams();
  const withAnswers = searchParams.get("answers") === "1";

  const paperQuery = useQuery({
    queryKey: ["question-bank", "print", id, withAnswers],
    queryFn: () => questionBankService.getPrintPaper(id, withAnswers),
    enabled: Number.isFinite(id),
  });

  const paper = paperQuery.data?.paper;

  const toggleAnswers = () => {
    setSearchParams(withAnswers ? {} : { answers: "1" }, { replace: true });
  };

  if (paperQuery.isLoading) {
    return <div className="dashboard-state"><Spinner size={22} /><p>Preparing paper...</p></div>;
  }
  if (!paper) {
    return <p>Paper not found.</p>;
  }

  // Continuous question numbering across sections
  let questionNumber = 0;

  return (
    <>
      <div className="no-print institution-directory-toolbar" style={{ gap: 10 }}>
        <Link to="/institution/examinations/papers" className="btn btn-ghost">
          <ArrowLeft size={15} /> Back to papers
        </Link>
        <Button variant="secondary" onClick={toggleAnswers}>
          {withAnswers ? <EyeOff size={15} /> : <Eye size={15} />}
          {withAnswers ? "Hide model answers" : "Show model answers"}
        </Button>
        <Button onClick={() => window.print()}>
          <Printer size={15} /> Print
        </Button>
      </div>

      <div className="exam-paper">
        <div className="exam-paper-header">
          <div className="exam-paper-institution">{paper.subject?.name}</div>
          <div className="exam-paper-code institution-mono">{paper.code}</div>
        </div>

        <div className="exam-paper-meta">
          <span>Time: 3 hours</span>
          <span>Maximum Marks: {paper.total_marks}</span>
          <span>{paper.subject?.code}</span>
        </div>

        {paper.instructions && (
          <div className="exam-paper-instructions">{paper.instructions}</div>
        )}

        {paper.sections.map((section) => (
          <div key={section.id} className="exam-paper-section">
            <h3>{section.title}</h3>
            {section.instruction && (
              <div className="exam-paper-section-instruction">{section.instruction}</div>
            )}
            {section.items.map((item) => {
              questionNumber += 1;
              return (
                <div key={item.id} className="exam-paper-item">
                  <div className="exam-paper-qrow">
                    <span className="exam-paper-qnum">{questionNumber}.</span>
                    <div className="exam-paper-qbody">
                      <p>{item.question_text}</p>
                      {withAnswers && item.model_answer && (
                        <div className="exam-paper-answer">
                          <strong>Model answer:</strong> {item.model_answer}
                        </div>
                      )}
                    </div>
                    <div className="exam-paper-qmarks">[{item.marks}]</div>
                  </div>
                </div>
              );
            })}
          </div>
        ))}

        <div className="exam-paper-end">*** End of question paper ***</div>
      </div>
    </>
  );
};

export default QuestionPaperPrintPage;
