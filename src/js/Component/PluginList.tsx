import React, { useEffect, useState } from 'react';
import Loader from '@/js/Utils/Loader';
import { getPluginList, safeParseJSON } from '@/js/Utils/Data';
import PluginCard from '@/js/Component/PluginCard';
import type { Plugin } from '@/js/Component/PluginCard';

function PluginList() {
    const [pluginList, setPluginList] = useState<Plugin[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    const getThePluginList = async () => {
        setIsLoading(true);
        try {
            const response = await getPluginList();
            const preparedData = safeParseJSON<Plugin[]>(response.data);
            setPluginList((preparedData as Plugin[]) || []);
        } finally {
            setIsLoading(false);
        }
    };

    const decodeHTMLEntities = (text: string): string => {
        if (typeof window === 'undefined') {
            return text;
        }
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    };

    useEffect(() => {
        void getThePluginList();
    }, []);

    return (
        <div className="p-6 md:p-10 min-h-[85vh]">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-800 mb-2">Our Plugins</h1>
                    <p className="text-gray-500">Explore our collection of powerful WordPress plugins to enhance your website.</p>
                </div>

                {isLoading ? (
                    <div className="flex flex-col justify-center items-center min-h-[50vh] bg-white rounded-xl border border-gray-100">
                        <Loader />
                        <p className="mt-4 text-gray-500">Loading plugins...</p>
                    </div>
                ) : pluginList.length > 0 ? (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {pluginList.map((plugin, index) => (
                            <PluginCard
                                key={index}
                                plugin={plugin}
                                iframeUrl={decodeHTMLEntities(plugin.TB_iframe)}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col justify-center items-center min-h-[50vh] bg-white rounded-xl border border-gray-100">
                        <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                        </div>
                        <p className="text-gray-500">No plugins found</p>
                    </div>
                )}
            </div>
    );
}

export default PluginList;
