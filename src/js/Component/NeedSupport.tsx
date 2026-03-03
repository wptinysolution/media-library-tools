
function NeedSupport() {
    return (
        <div className="p-6 md:p-10">
                <div className="text-center mb-10">
                    <h1 className="text-3xl font-bold text-gray-800 mb-3">
                        Need Help?
                    </h1>
                </div>

                <div className="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                    <a
                        href="https://help.wptinysolutions.com/"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group block bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg hover:border-blue-200 transition-all duration-300"
                    >
                        <div className="flex items-start gap-4">
                            <div className="flex-shrink-0 w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-500 transition-colors duration-300">
                                <svg className="w-7 h-7 text-blue-600 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-xl m-0! font-semibold text-gray-800 group-hover:text-blue-600 transition-colors">
                                    Support Center
                                </h3>
                                <p className="text-gray-500 mb-4">
                                    Create a support ticket and our team will respond promptly with a solution.
                                </p>
                                <span className="inline-flex items-center text-blue-600 font-medium group-hover:gap-2 transition-all">
                                    Visit Support
                                    <svg className="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>

                    <a
                        href="https://www.wptinysolutions.com/tiny-products/"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group block bg-white rounded-xl p-8 shadow-sm border border-gray-100 hover:shadow-lg hover:border-purple-200 transition-all duration-300"
                    >
                        <div className="flex items-start gap-4">
                            <div className="flex-shrink-0 w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center group-hover:bg-purple-500 transition-colors duration-300">
                                <svg className="w-7 h-7 text-purple-600 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                            <div className="flex-1">
                                <h3 className="text-xl m-0! font-semibold text-gray-800 group-hover:text-purple-600 transition-colors">
                                    Our Plugins
                                </h3>
                                <p className="text-gray-500 mb-4">
                                    Explore our collection of powerful WordPress plugins to enhance your site.
                                </p>
                                <span className="inline-flex items-center text-purple-600 font-medium group-hover:gap-2 transition-all">
                                    View Plugins
                                    <svg className="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>

                <div className="mt-10 max-w-4xl mx-auto">
                    <div className="bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl p-6 border border-blue-100">
                        <div className="flex items-start gap-4">
                            <div className="flex-shrink-0">
                                <svg className="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 className="font-semibold mt-0! mb-1  text-gray-800">Quick Tip</h4>
                                <p className="m-0! text-gray-600 text-sm">
                                    For faster support, please include details about your issue, WordPress version, and any error messages you've encountered.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    );
}

export default NeedSupport;
