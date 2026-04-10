interface UsedWhereImageCardProps {
    usage: any;
    isSelected: boolean;
    isExpanded: boolean;
    activeFilter: 'used' | 'unused' | 'trash';
    onSelect: () => void;
    onToggleExpand: () => void;
}

export default function UsedWhereImageCard({
    usage,
    isSelected,
    isExpanded,
    activeFilter,
    onSelect,
    onToggleExpand,
}: UsedWhereImageCardProps) {
    const posts: any[] = usage.posts || [];

    return (
        <>
            {/* Main row */}
            <div
                className={`flex items-center gap-4 p-4 ${posts.length > 0 ? 'cursor-pointer' : ''}`}
                onClick={() => posts.length > 0 && onToggleExpand()}
            >
                {/* Checkbox for unused and trash tabs */}
                {(activeFilter === 'unused' || activeFilter === 'trash') && (
                    <div
                        className="shrink-0"
                        onClick={(e) => { e.stopPropagation(); onSelect(); }}
                    >
                        <input
                            type="checkbox"
                            className="w-4 h-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                            checked={isSelected}
                            onChange={() => onSelect()}
                            onClick={(e) => e.stopPropagation()}
                        />
                    </div>
                )}

                {activeFilter === 'used' && (
                    posts.length > 0 ? (
                        <svg
                            className={`w-4 h-4 shrink-0 text-gray-400 transition-transform ${isExpanded ? 'rotate-90' : ''}`}
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    ) : (
                        <div className="w-4 h-4 shrink-0" />
                    )
                )}

                {/* Image thumbnail */}
                <div className="shrink-0 w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                    {usage.url ? (
                        <img src={usage.url} alt={usage.title} className="w-full h-full object-cover" />
                    ) : (
                        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    )}
                </div>

                {/* Title and URL */}
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm mt-0! mb-1.5 font-semibold text-gray-900 truncate">
                        {usage.title || `(ID: ${usage.attachment_id})`}
                    </h3>
                    <p className="text-xs mt-0! mb-0! text-gray-500 truncate">{usage.url}</p>
                </div>

                {/* Right side info */}
                <div className="shrink-0 flex flex-col items-start gap-3 text-xs">
                    <span className="inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded">
                        ID: #{usage?.attachment_id}
                    </span>
                    {activeFilter !== 'trash' && (
                        <>
                            {usage.usage_count > 0 ? (
                                <div className={`flex items-center`}>
                                    <span className="inline-flex items-center px-2 py-1 font-medium text-gray-700 bg-gray-100 rounded">
                                        {usage.usage_count} usage{usage.usage_count !== 1 ? 's' : ''}
                                    </span>
                                    <span className="inline-flex items-center px-2 py-1 font-medium text-blue-700 bg-blue-50 rounded">
                                        {usage.used_in_posts} post{usage.used_in_posts !== 1 ? 's' : ''}
                                    </span>
                                    {Object.entries(usage.usage_by_type || {}).map(([type, count]: [string, any]) => (
                                        <span key={type} className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 bg-emerald-50 rounded">
                                            {type}: {count}
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <span className="inline-flex items-center px-2 py-1 font-medium text-red-700 bg-red-50 rounded">
                                    No uses found
                                </span>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Expanded posts list */}
            {isExpanded && posts.length > 0 && (
                <div className="border-t border-gray-100 bg-gray-50 px-4 py-3">
                    <div className="space-y-1.5">
                        {posts.map((post: any, idx: number) => (
                            <div key={idx} className="flex items-center gap-3 text-sm py-2 px-3 bg-white rounded border border-gray-100">
                                <span className="flex-1 min-w-0 truncate font-medium text-gray-800">
                                    {post.post_title || `(ID: ${post.post_id})`}
                                </span>
                                <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-purple-700 bg-purple-50 rounded">
                                    {post.post_type}
                                </span>
                                <span className="shrink-0 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-amber-700 bg-amber-50 rounded">
                                    {post.usage_type}
                                </span>
                                {post.post_link && (
                                    <a
                                        href={post.post_link}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="shrink-0 text-xs text-blue-600 hover:text-blue-700"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        View
                                    </a>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </>
    );
}
