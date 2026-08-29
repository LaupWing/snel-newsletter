// Shared REST shapes, mirrored from the PHP responses. Local one-off types
// stay in their own file; only add here what more than one screen uses.

export type SubscriberStatus =
	| 'active'
	| 'inactive'
	| 'unsubscribed'
	| 'bounced'
	| 'complained';

// Row from GET /subscribers. wpdb returns every column as a string, so id
// and created_at arrive as strings; tags are attached server-side.
export interface Subscriber {
	id: string;
	email: string;
	name: string;
	status: SubscriberStatus;
	tags: string[];
	created_at: string;
}

export type CampaignStatus =
	| 'draft'
	| 'scheduled'
	| 'sending'
	| 'sent'
	| 'failed'
	| 'cancelled';

// Row from GET /campaigns — Campaigns\Model::format().
export interface Campaign {
	id: number;
	subject: string;
	status: CampaignStatus;
	type: 'broadcast' | 'workflow';
	automation_name: string;
	recipients: number;
	sent: number;
	opened: number;
	clicked: number;
	tags: string[];
	sent_at: string | null;
	created_at: string;
	edit_url: string | null;
}

export type MetricField =
	| 'open_rate'
	| 'click_rate'
	| 'opens'
	| 'clicks'
	| 'emails_received';

export type MetricOperator = 'gt' | 'gte' | 'lt' | 'lte' | 'eq';

export type FilterField =
	| MetricField
	| 'opened_in_days'
	| 'clicked_in_days'
	| 'status'
	| 'tag'
	| 'search';

export type FilterOperator = MetricOperator | 'has' | 'not_has' | 'is' | 'within';

// One row in the advanced filter; the server ANDs them (Model::build_conditions).
export interface FilterRule {
	field: FilterField;
	operator: FilterOperator;
	value: string;
}

// Row from GET /tags — Model::all_tags(). Static tags have null rule fields;
// threshold is a wpdb float column, so it arrives as a string.
export interface TagRule {
	tag: string;
	count: number;
	type: 'static' | 'dynamic';
	metric: MetricField | null;
	operator: MetricOperator | null;
	threshold: string | null;
}

// One CPT/custom source config — CptSources\Store::defaults().
export interface SourceConfig {
	id: string;
	kind: 'cpt' | 'custom';
	post_type: string | null;
	email_field: string;
	tag_field: string;
	tag_source: 'meta' | 'taxonomy';
	manual_tags: string[];
	auto_sync: boolean;
	last_sync: string | null;
	last_result: SourceSyncResult | null;
}

export interface SourceSyncResult {
	imported: number;
	tagged: number;
	skipped: number;
	invalid: number;
	junk: number;
}

declare global {
	interface Window {
		// inc/core/admin.php — wp_localize_script casts scalars to strings.
		snelNewsletter?: {
			restUrl: string;
			nonce: string;
			version: string;
		};
		// inc/core/editor.php — subscriberCount is a top-level scalar, so string.
		snelNewsletterEditor?: {
			restUrl: string;
			nonce: string;
			subscriberCount: string;
			tags: string[];
			tagCounts: Record< string, number >;
			senders: {
				broadcast: string;
				automation: string;
			};
		};
	}
}

export {};
