import React from 'react';
import { useStore } from '@/js/Utils/store';
import SettingRow from '@/js/Component/Common/SettingRow';

export default function AiSettings() {
    const { options, setOptions } = useStore();

    return (
        <div className="bg-white rounded-lg border border-gray-200">
            <div className="px-6 py-5 border-b border-gray-200">
                <h3 className="text-xl m-0! font-semibold text-gray-900">AI Settings</h3>
            </div>
            <div className="p-6 space-y-6">
                <SettingRow label="AI Provider:">
                    <div className="flex flex-wrap gap-6">
                        {(['chatgpt', 'gemini', 'claude'] as const).map((provider) => (
                            <label key={provider} className="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="ai_provider"
                                    value={provider}
                                    className="w-4 h-4 border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                    checked={(options.ai_provider ?? 'chatgpt') === provider}
                                    onChange={() => setOptions({ ai_provider: provider })}
                                />
                                <span className="text-base text-gray-900">
                                    {provider === 'chatgpt' ? 'ChatGPT' : provider === 'gemini' ? 'Gemini' : 'Claude'}
                                </span>
                            </label>
                        ))}
                    </div>
                    <p className="text-sm text-gray-500">Select which AI provider to use. All API keys you enter are saved; only the active provider&apos;s key is used.</p>
                </SettingRow>

                <SettingRow label="ChatGPT API Key:" bordered>
                    <input
                        type="password"
                        name="ai_chatgpt_key"
                        className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="sk-..."
                        value={(options.ai_chatgpt_key as string) || ''}
                        onChange={(e) => setOptions({ ai_chatgpt_key: e.target.value })}
                    />
                    <p className="text-sm text-gray-500">OpenAI API key for GPT-4o-mini vision.</p>
                </SettingRow>

                <SettingRow label="Gemini API Key:" bordered>
                    <input
                        type="password"
                        name="ai_gemini_key"
                        className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="AIza..."
                        value={(options.ai_gemini_key as string) || ''}
                        onChange={(e) => setOptions({ ai_gemini_key: e.target.value })}
                    />
                    <p className="text-sm text-gray-500">Google Gemini API key for gemini-2.0-flash.</p>
                </SettingRow>

                <SettingRow label="Claude API Key:" bordered>
                    <input
                        type="password"
                        name="ai_claude_key"
                        className="w-full max-w-md px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="sk-ant-..."
                        value={(options.ai_claude_key as string) || ''}
                        onChange={(e) => setOptions({ ai_claude_key: e.target.value })}
                    />
                    <p className="text-sm text-gray-500">Anthropic API key for Claude Haiku.</p>
                </SettingRow>
            </div>
        </div>
    );
}
