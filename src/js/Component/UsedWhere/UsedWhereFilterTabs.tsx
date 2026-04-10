type FilterTab = 'used' | 'unused' | 'trash';

interface Tab {
    key: FilterTab;
    label: string;
}

const tabs: Tab[] = [
    { key: 'unused', label: 'Unused' },
    { key: 'used', label: 'Used' },
    { key: 'trash', label: 'Trash' },
];

interface UsedWhereFilterTabsProps {
    activeFilter: FilterTab;
    totalUsages: number;
    isLoading: boolean;
    onTabChange: (filter: FilterTab) => void;
}

export default function UsedWhereFilterTabs({
    activeFilter,
    totalUsages,
    isLoading,
    onTabChange,
}: UsedWhereFilterTabsProps) {
    return (
        <div className="flex bg-white border-b border-gray-200">
            {tabs.map((tab) => (
                <button
                    key={tab.key}
                    type="button"
                    className={`px-5 py-3 text-sm font-medium border-b-2 transition-colors cursor-pointer ${
                        activeFilter === tab.key
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    }`}
                    onClick={() => onTabChange(tab.key)}
                >
                    {tab.label}
                    {!isLoading && activeFilter === tab.key && (
                        <span className="ml-2 inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-gray-600 bg-gray-100 rounded-full">
                            {totalUsages}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}
