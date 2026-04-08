import { useState } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { X, Monitor, Smartphone } from 'lucide-react';

export default function EmailPreviewModal( { onClose, title, previewText, content } ) {
    const [ device, setDevice ] = useState( 'desktop' );

    return createPortal(
        <div className="snel-nl-preview-overlay" onClick={ onClose }>
            <div className="snel-nl-preview-modal" onClick={ ( e ) => e.stopPropagation() }>
                <div className="snel-nl-preview-header">
                    <h3>{ __( 'Email Preview', 'snel-newsletter' ) }</h3>
                    <div className="snel-nl-preview-actions">
                        <button
                            type="button"
                            onClick={ () => setDevice( 'desktop' ) }
                            className={ `snel-nl-device-btn ${ device === 'desktop' ? 'is-active' : '' }` }
                        >
                            <Monitor size={ 14 } />
                        </button>
                        <button
                            type="button"
                            onClick={ () => setDevice( 'mobile' ) }
                            className={ `snel-nl-device-btn ${ device === 'mobile' ? 'is-active' : '' }` }
                        >
                            <Smartphone size={ 14 } />
                        </button>
                        <button type="button" onClick={ onClose } className="snel-nl-preview-close">
                            <X size={ 16 } />
                        </button>
                    </div>
                </div>

                <div className="snel-nl-preview-body">
                    <div className={ `snel-nl-preview-frame ${ device === 'mobile' ? 'is-mobile' : '' }` }>
                        {/* Inbox row preview */}
                        <div className="snel-nl-inbox-preview">
                            <div className="snel-nl-inbox-row">
                                <div className="snel-nl-inbox-sender">Your Newsletter</div>
                                <div className="snel-nl-inbox-date">now</div>
                            </div>
                            <div className="snel-nl-inbox-subject">{ title || __( '(no subject)', 'snel-newsletter' ) }</div>
                            <div className="snel-nl-inbox-preview-text">{ previewText || __( 'No preview text set...', 'snel-newsletter' ) }</div>
                        </div>

                        {/* Email content preview */}
                        <div className="snel-nl-email-preview">
                            <table width="100%" cellPadding="0" cellSpacing="0" style={ { margin: '0 auto', fontFamily: 'Arial, sans-serif', maxWidth: '600px' } }>
                                <tbody>
                                    <tr>
                                        <td style={ { background: '#1a1a1a', padding: '24px', textAlign: 'center' } }>
                                            <span style={ { color: '#fff', fontSize: '18px', fontWeight: 'bold' } }>Your Newsletter</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={ { padding: '32px 24px', background: '#ffffff' } }>
                                            { content ? (
                                                <div
                                                    className="snel-nl-email-content"
                                                    dangerouslySetInnerHTML={ { __html: content } }
                                                />
                                            ) : (
                                                <p style={ { color: '#9ca3af', fontStyle: 'italic', fontSize: '14px' } }>
                                                    { __( 'Start writing your newsletter content in the editor...', 'snel-newsletter' ) }
                                                </p>
                                            ) }
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={ { padding: '16px 24px', textAlign: 'center', borderTop: '1px solid #e5e7eb' } }>
                                            <p style={ { color: '#9ca3af', fontSize: '11px', margin: '0 0 4px' } }>
                                                { __( 'You received this because you subscribed to our newsletter.', 'snel-newsletter' ) }
                                            </p>
                                            <a href="#" style={ { color: '#6b7280', fontSize: '11px' } }>
                                                { __( 'Unsubscribe', 'snel-newsletter' ) }
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
