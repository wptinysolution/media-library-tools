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
    hasWoo: boolean;
    proVersion?: boolean;
};

declare const cptwoointParams: {
    pluginUrl: string;
    [key: string]: unknown;
};
