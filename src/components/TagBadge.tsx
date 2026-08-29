import { X } from 'lucide-react';

type Props = {
    tag: string;
    removable?: boolean;
    onRemove?: () => void;
};

export default function TagBadge( { tag, removable, onRemove }: Props ) {
    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-purple-50 text-purple-700 rounded-full">
            { tag }
            { removable && (
                <button type="button" onClick={ onRemove } className="hover:text-purple-900 transition-colors">
                    <X size={ 10 } />
                </button>
            ) }
        </span>
    );
}
