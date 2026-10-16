// Global variables injected by WordPress via wp_localize_script
declare const tsmltParams: {
    ajaxUrl: string;
    tsmlt_wpnonce: string;
    restApiUrl: string;
    rest_nonce: string;
    includesUrl: string;
    uploadUrl: string;
    proLink: string;
    hasExtended: boolean;
    /** MCP Docs admin page URL; empty for users who cannot open it. */
    mcpDocsUrl?: string;
    hasWoo: boolean;
    proVersion?: boolean;
    iconUrl: string;
};

declare const cptwoointParams: {
    pluginUrl: string;
    [key: string]: unknown;
};
