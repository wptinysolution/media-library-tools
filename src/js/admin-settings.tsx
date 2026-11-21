import ReactDOM from 'react-dom/client';
import App from '@/js/Component/App';
import McpPage from '@/js/Component/Mcp/McpPage';
import { setupNavigation } from "@/js/navigation";
import '@/css/index.css';
import '@/scss/admin-settings.scss';

// Media › MCP has its own mount point; every other plugin page uses the app.
const mcpRoot = document.getElementById('tsmlt_mcp_root');
if (mcpRoot) {
    ReactDOM.createRoot(mcpRoot).render(<McpPage />);
} else {
    const root = ReactDOM.createRoot(document.getElementById('media_root') as HTMLElement);

    root.render(
        <App />
    );

    setupNavigation();
}
