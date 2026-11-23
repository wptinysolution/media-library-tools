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
    /** MCP Connection admin page URL; empty for users who cannot open it. */
    mcpDocsUrl?: string;
    /** MCP page data (Media › MCP); null elsewhere. */
    mcp?: McpPageSettings | null;
    hasWoo: boolean;
    proVersion?: boolean;
    iconUrl: string;
};

interface McpPageSettings {
    enabled: boolean;
    serverUrl: string;
    dedicatedUrl: string;
    serverName: string;
    /** Current user's login, for the manual Authorization value. */
    username: string;
    adapterStatus: 'active' | 'inactive' | 'missing' | string;
    abilitiesSupported: boolean;
    defaultServerSupported: boolean;
    canCreatePassword: boolean;
    passwordWarning: string;
    docsUrl: string;
    profileUrl: string;
}

declare const cptwoointParams: {
    pluginUrl: string;
    [key: string]: unknown;
};
