import { __ } from '@wordpress/i18n';
import { Send } from 'lucide-react';

export default function Campaigns() {
    return (
        <div className="p-6">
            <div className="mb-8">
                <h1 className="text-xl font-bold text-gray-900">
                    Snel <em className="font-serif font-normal italic">Newsletter</em>
                </h1>
                <p className="text-sm text-gray-500 mt-1">{ __( 'Create and manage campaigns', 'snel-newsletter' ) }</p>
            </div>
            <div className="bg-white border border-gray-200 rounded-lg px-5 py-12 text-center">
                <Send size={ 32 } className="mx-auto text-gray-300 mb-3" />
                <p className="text-sm text-gray-500">{ __( 'Campaigns page — coming soon', 'snel-newsletter' ) }</p>
            </div>
        </div>
    );
}
