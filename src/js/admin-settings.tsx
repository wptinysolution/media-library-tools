import ReactDOM from 'react-dom/client';
import App from '@/js/Component/App';
import McpPage from '@/js/Component/Mcp/McpPage';
import { setupNavigation } from "@/js/navigation";
import '@/css/index.css';
import '@/scss/admin-settings.scss';

// The MCP Connection tab has its own mount point; the settings page uses the app.
const mcpRoot = document.getElementById('tsmlt_mcp_root');
if (mcpRoot) {
    ReactDOM.createRoot(mcpRoot).render(<McpPage />);
} else {
    const mediaRoot = document.getElementById('media_root');
    if (mediaRoot) {
        ReactDOM.createRoot(mediaRoot).render(
            <App />
        );

        setupNavigation();
    }
}
