const path = require('path');

module.exports = (Encore) => {
    Encore.addEntry('ibexa-admin-ui-ai-settings-react-js', [
        path.resolve(__dirname, '../public/admin/js/ai-settings.js'),
    ]);

    Encore.addEntry('ibexa-admin-ui-ai-settings-react-css', [
        path.resolve(__dirname, '../public/admin/scss/_ai-settings-dashboard.scss'),
    ]);
};
