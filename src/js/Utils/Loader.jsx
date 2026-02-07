function Loader({ size = 'md', fullScreen = false, text }) {
    const sizeClasses = {
        sm: 'w-6 h-6',
        md: 'w-10 h-10',
        lg: 'w-14 h-14'
    };

    const containerClasses = fullScreen
        ? 'h-[90vh] flex items-center justify-center'
        : 'flex items-center justify-center py-8';

    return (
        <div className={containerClasses}>
            <div className="flex flex-col items-center gap-4">
                {/* Loader Animation */}
                <div className="relative">
                    {/* Outer ring */}
                    <div className={`${sizeClasses[size]} rounded-full border-4 border-blue-100`}></div>
                    {/* Spinning arc */}
                    <div className={`${sizeClasses[size]} rounded-full border-4 border-transparent border-t-blue-600 animate-spin absolute top-0 left-0`}></div>
                    {/* Inner pulse */}
                    <div className={`absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 ${size === 'lg' ? 'w-4 h-4' : size === 'md' ? 'w-2.5 h-2.5' : 'w-1.5 h-1.5'} bg-blue-600 rounded-full animate-pulse`}></div>
                </div>

                {/* Optional text */}
                {text && (
                    <p className="text-sm text-gray-500 font-medium animate-pulse">{text}</p>
                )}
            </div>
        </div>
    );
}

export default Loader;