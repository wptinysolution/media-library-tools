import React from "react";
import { useStore } from "@/js/Utils/store";
import { clearSchedule } from "@/js/Utils/Data";
import { Link, useLocation } from "react-router-dom";

interface MenuItem {
    key: string;
    label: string;
    icon: React.ReactNode;
}

function MainHeader() {
    const { pathname } = useLocation();
    const { setBulkSubmitData, generalData, setGeneralData } = useStore();
    const isCollapsed = generalData.sidebarCollapsed;

    const basePath = pathname.replace(/\/page\/\d+$/, '');
    const pat = basePath;

    const menuItems: MenuItem[] = [
        {
            key: '/',
            label: 'Media Settings',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            ),
        },
        {
            key: '/mediaTable',
            label: 'Media Table',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            ),
        },
        {
            key: '/mediaRename',
            label: 'Media Rename',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            ),
        },
        {
            key: '/export',
            label: 'CSV Export',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
            ),
        },
        {
            key: '/import',
            label: 'CSV Import',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            ),
        },
        {
            key: '/rubbishFile',
            label: 'Rubbish files',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            ),
        },
        {
            key: '/imageSize',
            label: 'Image Size',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z" />
                </svg>
            ),
        },
        {
            key: '/mediaDownload',
            label: 'Media Download',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            ),
        },
        {
            key: '/plugins',
            label: 'Useful Plugins',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                </svg>
            ),
        },
        {
            key: '/support',
            label: 'Get Support',
            icon: (
                <svg className="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            ),
        },
    ];

    const onMenuSelect = (key: string) => {
        if (key === '/rubbishFile') {
            clearSchedule();
        }
        setBulkSubmitData({ bulkChecked: false, ids: [] });
    };

    const toggleCollapse = () => {
        const next = !isCollapsed;
        setGeneralData({ sidebarCollapsed: next });
        localStorage.setItem('mlt_sidebar_collapsed', String(next));
    };

    return (
        <nav
            style={{ width: isCollapsed ? 48 : 200 }}
            className="fixed h-full shrink-0 z-9000 bg-white border-r border-gray-200 flex flex-col transition-[width] duration-200 ease-in-out"
        >
            {/* Navigation items */}
            <div className="flex flex-col pt-5 flex-1 overflow-y-auto overflow-x-hidden">
                {menuItems.map((item) => {
                    const isActive = pat === item.key;
                    return (
                        <Link
                            key={item.key}
                            to={item.key}
                            onClick={() => onMenuSelect(item.key)}
                            title={isCollapsed ? item.label : undefined}
                            className={`relative flex items-center gap-3 py-2.5 text-[12.5px] font-medium transition-colors no-underline shadow-none! outline-0! focus:outline-0! whitespace-nowrap border-b border-gray-100 ${
                                isCollapsed ? 'justify-center px-3' : 'px-4'
                            } ${
                                isActive
                                    ? 'bg-blue-50! text-blue-700!'
                                    : 'text-gray-500! hover:text-gray-900! hover:bg-gray-50!'
                            }`}
                        >
                            {/* Active left accent bar */}
                            {isActive && (
                                <span className="absolute left-0 top-0 bottom-0 w-[3px] bg-blue-600 rounded-r-sm" />
                            )}
                            {item.icon}
                            {!isCollapsed && item.label}
                        </Link>
                    );
                })}
            </div>

            {/* Collapse toggle — right-edge tab, vertically centered, icon only */}
            <button
                type="button"
                onClick={toggleCollapse}
                title={isCollapsed ? 'Expand Menu' : 'Collapse Menu'}
                className="absolute -right-5 top-1/2 -translate-y-1/2 w-5 h-10 bg-white border border-l-0 border-gray-200 rounded-r-lg flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-50 cursor-pointer transition-colors z-10"
            >
                <svg
                    className={`w-3 h-3 shrink-0 transition-transform duration-200 ${isCollapsed ? 'rotate-180' : ''}`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
            </button>
        </nav>
    );
}

export default MainHeader;
