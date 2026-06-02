const path = require('path');

module.exports = (Encore) => {
    Encore.addEntry('ibexa-admin-ui-ai-settings-react-js', [
        path.resolve(__dirname, '../public/admin/js/ai-settings.js'),
    ]);

    Encore.addEntry('ibexa-admin-ui-content-edit-parts-js', [
        path.resolve(__dirname, '../public/admin/js/ai-suggest.js'),
    ]);

    Encore.addEntry('ibexa-admin-ui-content-edit-parts-css', [
        path.resolve(__dirname, '../public/admin/css/_ai-suggest.scss'),
    ]);
};
