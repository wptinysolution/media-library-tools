
interface ProgressBarProps {
    percent: number;
}

export default function ProgressBar({ percent }: ProgressBarProps) {
    return (
        <div className="w-full">
            <div className="flex justify-between items-center mb-1">
                <span className="text-xs font-medium text-gray-600">{percent}%</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                <div
                    className="bg-blue-600 h-2.5 rounded-full transition-all duration-300"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
