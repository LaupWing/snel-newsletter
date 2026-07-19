import { __ } from '@wordpress/i18n';
import { Plus, X } from 'lucide-react';
import Select from '../../components/Select';

/**
 * Stacked advanced-filter builder for the subscriber list.
 *
 * Emits an array of { field, operator, value } conditions, all AND-ed on the
 * server. Metric fields (open rate, clicks, …) compare numerically; status and
 * tag compare against a fixed set. Two rows on the same field give ranges, e.g.
 * open_rate > 50 AND open_rate < 80.
 *
 * @param {Object}   props
 * @param {Array}    props.filters  - Current conditions.
 * @param {Function} props.onChange - Called with the next conditions array.
 * @param {Array}    props.allTags  - Available tag names.
 */

const METRIC_FIELDS = [
    { value: 'open_rate',       label: __( 'Open rate', 'snel-newsletter' ),       unit: '%' },
    { value: 'click_rate',      label: __( 'Click rate', 'snel-newsletter' ),      unit: '%' },
    { value: 'opens',           label: __( 'Total opens', 'snel-newsletter' ),     unit: '' },
    { value: 'clicks',          label: __( 'Total clicks', 'snel-newsletter' ),    unit: '' },
    { value: 'emails_received', label: __( 'Emails received', 'snel-newsletter' ), unit: '' },
];

const METRIC_OPERATORS = [
    { value: 'gt',  label: __( 'higher than', 'snel-newsletter' ) },
    { value: 'gte', label: __( 'at least', 'snel-newsletter' ) },
    { value: 'lt',  label: __( 'lower than', 'snel-newsletter' ) },
    { value: 'lte', label: __( 'at most', 'snel-newsletter' ) },
    { value: 'eq',  label: __( 'equal to', 'snel-newsletter' ) },
];

const TAG_OPERATORS = [
    { value: 'has',     label: __( 'has tag', 'snel-newsletter' ) },
    { value: 'not_has', label: __( 'missing tag', 'snel-newsletter' ) },
];

const STATUSES = [
    { value: 'active',       label: __( 'Active', 'snel-newsletter' ) },
    { value: 'unsubscribed', label: __( 'Unsubscribed', 'snel-newsletter' ) },
    { value: 'bounced',      label: __( 'Bounced', 'snel-newsletter' ) },
    { value: 'inactive',     label: __( 'Inactive', 'snel-newsletter' ) },
    { value: 'complained',   label: __( 'Complained', 'snel-newsletter' ) },
];

const FIELD_OPTIONS = [
    ...METRIC_FIELDS.map( ( m ) => ( { value: m.value, label: m.label } ) ),
    { value: 'status', label: __( 'Status', 'snel-newsletter' ) },
    { value: 'tag',    label: __( 'Tag', 'snel-newsletter' ) },
];

const isMetric = ( field ) => METRIC_FIELDS.some( ( m ) => m.value === field );

/** Sensible default operator + value when the field changes. */
function defaultsFor( field ) {
    if ( field === 'status' ) return { operator: 'is', value: 'active' };
    if ( field === 'tag' )    return { operator: 'has', value: '' };
    return { operator: 'gt', value: '' };
}

export default function FilterBar( { filters, onChange, allTags } ) {
    const addRow = () => {
        onChange( [ ...filters, { field: 'open_rate', ...defaultsFor( 'open_rate' ) } ] );
    };

    const updateRow = ( index, patch ) => {
        onChange( filters.map( ( f, i ) => ( i === index ? { ...f, ...patch } : f ) ) );
    };

    const removeRow = ( index ) => {
        onChange( filters.filter( ( _, i ) => i !== index ) );
    };

    return (
        <div className="px-4 py-3 border-b border-gray-100 bg-gray-50/40 space-y-2">
            { filters.length === 0 && (
                <p className="text-xs text-gray-400">
                    { __( 'No filters. Add one to narrow the list by engagement, status, or tag.', 'snel-newsletter' ) }
                </p>
            ) }

            { filters.map( ( f, i ) => {
                const metric = isMetric( f.field );
                const unit   = METRIC_FIELDS.find( ( m ) => m.value === f.field )?.unit || '';

                return (
                    <div key={ i } className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs text-gray-400 w-10">
                            { i === 0 ? __( 'Where', 'snel-newsletter' ) : __( 'and', 'snel-newsletter' ) }
                        </span>

                        <Select
                            value={ f.field }
                            onChange={ ( v ) => updateRow( i, { field: v, ...defaultsFor( v ) } ) }
                            options={ FIELD_OPTIONS }
                        />

                        { metric && (
                            <Select
                                value={ f.operator }
                                onChange={ ( v ) => updateRow( i, { operator: v } ) }
                                options={ METRIC_OPERATORS }
                            />
                        ) }
                        { f.field === 'tag' && (
                            <Select
                                value={ f.operator }
                                onChange={ ( v ) => updateRow( i, { operator: v } ) }
                                options={ TAG_OPERATORS }
                            />
                        ) }
                        { f.field === 'status' && (
                            <span className="text-xs text-gray-400">{ __( 'is', 'snel-newsletter' ) }</span>
                        ) }

                        {/* Value input — number for metrics, dropdown otherwise. */}
                        { metric && (
                            <div className="relative">
                                <input
                                    type="number"
                                    value={ f.value }
                                    onChange={ ( e ) => updateRow( i, { value: e.target.value } ) }
                                    placeholder="0"
                                    step={ unit === '%' ? '0.1' : '1' }
                                    max={ unit === '%' ? '100' : undefined }
                                    min="0"
                                    className="w-24 pl-3 pr-6 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 focus:shadow-[0_0_0_1px_#3b82f6]"
                                />
                                { unit && <span className="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">{ unit }</span> }
                            </div>
                        ) }
                        { f.field === 'status' && (
                            <Select
                                value={ f.value }
                                onChange={ ( v ) => updateRow( i, { value: v } ) }
                                options={ STATUSES }
                            />
                        ) }
                        { f.field === 'tag' && (
                            <Select
                                value={ f.value }
                                onChange={ ( v ) => updateRow( i, { value: v } ) }
                                options={ [
                                    { value: '', label: __( 'Select tag…', 'snel-newsletter' ) },
                                    ...allTags.map( ( t ) => ( { value: t, label: t } ) ),
                                ] }
                            />
                        ) }

                        <button
                            type="button"
                            onClick={ () => removeRow( i ) }
                            className="p-1 text-gray-300 hover:text-red-500 transition-colors"
                            aria-label={ __( 'Remove filter', 'snel-newsletter' ) }
                        >
                            <X size={ 14 } />
                        </button>
                    </div>
                );
            } ) }

            <button
                type="button"
                onClick={ addRow }
                className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
            >
                <Plus size={ 12 } />
                { __( 'Add filter', 'snel-newsletter' ) }
            </button>
        </div>
    );
}
