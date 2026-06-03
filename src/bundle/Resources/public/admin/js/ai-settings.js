import React from 'react';
import ReactDOM from 'react-dom';
import AiSettingsDashboard from '../components/ai-settings/AiSettingsDashboard.jsx';
import '../scss/_ai-settings-dashboard.scss';
function mountAiSettings() {
    const container = document.getElementById('ai-settings-react-root');
    if (container) {
        if (typeof ReactDOM.createRoot === 'function') {
            ReactDOM.createRoot(container).render(<AiSettingsDashboard />);
        } else {
            ReactDOM.render(<AiSettingsDashboard />, container);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAiSettings);
} else {
    mountAiSettings();
}
