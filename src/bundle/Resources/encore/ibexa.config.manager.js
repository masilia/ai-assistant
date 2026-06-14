const path = require('path');

module.exports = (eZConfig, eZConfigManager) => {
    eZConfigManager.add({
        eZConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-js',
        newItems: [
            path.resolve(__dirname, '../public/admin/js/ai-suggest.js'),
        ],
    });

    eZConfigManager.add({
        eZConfig,
        entryName: 'ibexa-admin-ui-content-edit-parts-css',
        newItems: [
            path.resolve(__dirname, '../public/admin/scss/_ai-suggest.scss'),
        ],
    });

    eZConfigManager.add({
        eZConfig,
        entryName: 'ibexa-admin-ui-layout-js',
        newItems: [
            path.resolve(__dirname, '../public/admin/js/ai-agent-chat.js'),
        ],
    });

    eZConfigManager.add({
        eZConfig,
        entryName: 'ibexa-admin-ui-layout-css',
        newItems: [
            path.resolve(__dirname, '../public/admin/scss/_ai-agent-chat.scss'),
        ],
    });
};
