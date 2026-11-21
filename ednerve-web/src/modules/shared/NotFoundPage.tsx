import { Link } from "react-router-dom";
import { EmptyState } from "../../shared/ui";

const NotFoundPage = () => (
  <div className="page-container page-container-centered">
    <EmptyState
      title="Page not found"
      description="The page you are looking for does not exist."
      action={
        <Link to="/platform/dashboard" className="btn btn-primary">
          Go to Dashboard
        </Link>
      }
    />
  </div>
);

export default NotFoundPage;
