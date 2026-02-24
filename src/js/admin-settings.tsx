import React from 'react';
import ReactDOM from 'react-dom/client';
import App from '@/js/Component/App';
import { setupNavigation } from "@/js/navigation";
import '@/css/index.css';
import '@/scss/admin-settings.scss';

const root = ReactDOM.createRoot(document.getElementById('media_root') as HTMLElement);

root.render(
    <App />
);

setupNavigation();
