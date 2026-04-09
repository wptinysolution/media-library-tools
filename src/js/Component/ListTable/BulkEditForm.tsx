import React from 'react';
import Textarea from '@/js/Component/Common/Textarea';
import type { BulkSubmitDataItem } from '@/js/Utils/store';

const fields = [
    { name: 'post_title', label: 'Title', placeholder: 'Title' },
    { name: 'alt_text', label: 'Alt Text', placeholder: 'Alt text' },
    { name: 'caption', label: 'Caption', placeholder: 'Caption' },
    { name: 'post_description', label: 'Description', placeholder: 'Description' },
];

interface BulkEditFormProps {
    data: BulkSubmitDataItem;
    onChange: React.ChangeEventHandler<HTMLTextAreaElement>;
}

export default function BulkEditForm({ data, onChange }: BulkEditFormProps) {
    return (
        <div className="space-y-4">
            {fields.map(({ name, label, placeholder }) => (
                <div key={name}>
                    <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">{label}</h5>
                    <Textarea
                        rows={2}
                        onChange={onChange}
                        name={name}
                        value={data[name as keyof BulkSubmitDataItem]}
                        placeholder={placeholder}
                    />
                </div>
            ))}
        </div>
    );
}
