
export interface Plugin {
    plugin_name: string;
    short_description: string;
    slug: string;
    TB_iframe: string;
    icons?: { '2x'?: string; '1x'?: string };
}

interface PluginCardProps {
    plugin: Plugin;
    iframeUrl: string;
}

function PluginCard({ plugin, iframeUrl }: PluginCardProps) {
    return (
        <div className="group block bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-lg hover:border-blue-200 transition-all duration-300">
            <div className="flex flex-col h-full">
                <div className="p-6">
                    <div className="flex items-start gap-5">
                        <div className="shrink-0">
                            <a
                                href={iframeUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="thickbox open-plugin-details-modal block relative"
                            >
                                <div className="relative overflow-hidden rounded-xl">
                                    <img
                                        src={plugin?.icons?.['2x'] || plugin?.icons?.['1x']}
                                        alt={plugin.plugin_name}
                                        className="w-20 h-20 object-cover transition-transform duration-300 group-hover:scale-110"
                                    />
                                    <div className="absolute inset-0 bg-blue-600/0 group-hover:bg-blue-600/10 transition-colors duration-300"></div>
                                </div>
                            </a>
                        </div>
                        <div className="flex-1 min-w-0">
                            <a
                                href={iframeUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="thickbox open-plugin-details-modal block mb-2"
                            >
                                <h3 className="text-lg m-0! font-semibold text-gray-800 group-hover:text-blue-600 transition-colors line-clamp-2 leading-tight">
                                    <span dangerouslySetInnerHTML={{ __html: plugin.plugin_name }} />
                                </h3>
                            </a>
                            <p className="text-sm text-gray-500 line-clamp-3 leading-relaxed">
                                <span dangerouslySetInnerHTML={{ __html: plugin.short_description }} />
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mt-auto border-t border-gray-100 bg-gray-50/50 p-4">
                    <div className="flex gap-3">
                        <a
                            href={iframeUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="thickbox open-plugin-details-modal flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700! bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Details
                        </a>
                        <a
                            href={`https://www.wptinysolutions.com/tiny-products/${plugin.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium bg-blue-50 border border-blue-200 rounded-lg transition-all"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Website
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default PluginCard;
