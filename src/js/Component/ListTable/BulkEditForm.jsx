import React from "react";

const fields = [
    { name: 'post_title', label: 'Title', placeholder: 'Title' },
    { name: 'alt_text', label: 'Alt Text', placeholder: 'Alt text' },
    { name: 'caption', label: 'Caption', placeholder: 'Caption' },
    { name: 'post_description', label: 'Description', placeholder: 'Description' },
];

export default function BulkEditForm({ data, onChange }) {
    return (
        <div className="space-y-4">
            {fields.map(({ name, label, placeholder }) => (
                <div key={name}>
                    <h5 className="text-sm font-semibold text-gray-900 m-0! mb-1.5">{label}</h5>
                    <textarea
                        className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                        rows="2"
                        onChange={onChange}
                        name={name}
                        value={data[name]}
                        placeholder={placeholder}
                    />
                </div>
            ))}
        </div>
    );
}
