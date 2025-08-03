<!-- resources/views/outlook/emails.blade.php -->
@extends('outlook.layout.app')

@section('title', 'Outlook Emails')

@section('disconnect_button')
<a href="{{ route('outlook.disconnect') }}" class="btn btn-danger">Disconnect Outlook</a>
@endsection

@section('content')
<div class="container">
    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#inbox" id="inbox-tab">Inbox</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#sent" id="sent-tab">Sent Items</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Inbox Tab -->
        <div class="tab-pane fade show active" id="inbox">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Inbox</h2>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-primary" id="refresh-inbox">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <span class="badge bg-primary" id="inbox-count">Loading...</span>
                </div>
            </div>

            <div id="inbox-loading" class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading inbox...</p>
            </div>

            <div id="inbox-content" style="display: none;">
                <!-- Emails will be loaded here -->
            </div>
        </div>

        <!-- Sent Items Tab -->
        <div class="tab-pane fade" id="sent">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Sent Items</h2>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-primary" id="refresh-sent">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <span class="badge bg-primary" id="sent-count">0 emails</span>
                </div>
            </div>

            <div id="sent-loading" class="text-center" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading sent items...</p>
            </div>

            <div id="sent-content">
                <div class="alert alert-info">Click to load sent emails</div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    let inboxLoaded = false;
    let sentLoaded = false;

    // Load inbox immediately when page loads
    loadInbox();

    // Tab switch event listeners
    document.getElementById('inbox-tab').addEventListener('click', function() {
            loadInbox();
    });

    document.getElementById('sent-tab').addEventListener('click', function() {
            loadSent();
    });
 // Refresh button event listeners
    document.getElementById('refresh-inbox').addEventListener('click', function() {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        this.disabled = true;
        loadInbox(true); // Force refresh
    });

    document.getElementById('refresh-sent').addEventListener('click', function() {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        this.disabled = true;
        loadSent(true); // Force refresh
    });
    function loadInbox(forceRefresh = false) {        
        document.getElementById('inbox-loading').style.display = 'block';
        document.getElementById('inbox-content').style.display = 'none';
        const url = forceRefresh ? '/outlook/api/inbox?refresh=true' : '/outlook/api/inbox';

        fetch(url)
            .then(response => {
                if (response.status === 401) {
                    // Session expired, redirect to connect page
                    window.location.href = '/outlook/connect';
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (!data) return;
                if (data.error) {
                    throw new Error(data.error);
                }
                
                displayInboxEmails(data.emails);
                document.getElementById('inbox-count').textContent = data.emails.length + ' emails';
            })
            .catch(error => {
                console.error('Error loading inbox:', error);
                document.getElementById('inbox-content').innerHTML = 
                    '<div class="alert alert-danger">Failed to load inbox emails</div>';
                document.getElementById('inbox-content').style.display = 'block';
            })
            .finally(() => {
                document.getElementById('inbox-loading').style.display = 'none';
            });
    }

    function loadSent(forceRefresh = false) {
        document.getElementById('sent-loading').style.display = 'block';
        document.getElementById('sent-content').innerHTML = '';
        const url = forceRefresh ? '/outlook/api/sent?refresh=true' : '/outlook/api/sent';

        fetch(url)
            .then(response => {
                if (response.status === 401) {
                    // Session expired, redirect to connect page
                    window.location.href = '/outlook/connect';
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (!data) return;
                if (data.error) {
                    throw new Error(data.error);
                }
                
                displaySentEmails(data.emails);
                document.getElementById('sent-count').textContent = data.emails.length + ' emails';
            })
            .catch(error => {
                console.error('Error loading sent items:', error);
                document.getElementById('sent-content').innerHTML = 
                    '<div class="alert alert-danger">Failed to load sent emails</div>';
            })
            .finally(() => {
                document.getElementById('sent-loading').style.display = 'none';
            });
    }

    function displayInboxEmails(emails) {
        const content = document.getElementById('inbox-content');
        
        if (emails.length === 0) {
            content.innerHTML = '<div class="alert alert-info">No emails in your inbox</div>';
        } else {
            let html = '<div class="list-group">';
            
            emails.forEach(email => {
                const receivedDate = new Date(email.receivedDateTime);
                const timeAgo = getTimeAgo(receivedDate);
                const fromName = email.from?.emailAddress?.name || email.from?.emailAddress?.address || 'Unknown';
                const preview = email.bodyPreview ? email.bodyPreview.substring(0, 100) + (email.bodyPreview.length > 100 ? '...' : '') : '';
                
                html += `
                    <a href="/outlook/inbox/${email.id}" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${email.subject || 'No Subject'}</h6>
                            <small>${timeAgo}</small>
                        </div>
                        <div class="d-flex w-100 justify-content-between">
                            <p class="mb-1"><strong>From:</strong> ${fromName}</p>
                            ${email.hasAttachments ? '<span class="badge bg-secondary"><i class="fas fa-paperclip"></i> Attachment</span>' : ''}
                        </div>
                        <small class="text-muted">${preview}</small>
                    </a>
                `;
            });
            
            html += '</div>';
            content.innerHTML = html;
        }
        
        content.style.display = 'block';
    }

    function displaySentEmails(emails) {
        const content = document.getElementById('sent-content');
        
        if (emails.length === 0) {
            content.innerHTML = '<div class="alert alert-info">No sent emails</div>';
        } else {
            let html = '<div class="list-group">';
            
            emails.forEach(email => {
                const sentDate = new Date(email.sentDateTime);
                const timeAgo = getTimeAgo(sentDate);
                const toName = email.toRecipients?.[0]?.emailAddress?.name || email.toRecipients?.[0]?.emailAddress?.address || 'N/A';
                const preview = email.bodyPreview ? email.bodyPreview.substring(0, 100) + (email.bodyPreview.length > 100 ? '...' : '') : '';
                
                html += `
                    <a href="/outlook/sent/${email.id}" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${email.subject || 'No Subject'}</h6>
                            <small>${timeAgo}</small>
                        </div>
                        <div class="d-flex w-100 justify-content-between">
                            <p class="mb-1"><strong>To:</strong> ${toName}</p>
                            ${email.hasAttachments ? '<span class="badge bg-secondary"><i class="fas fa-paperclip"></i> Attachment</span>' : ''}
                        </div>
                        <small class="text-muted">${preview}</small>
                    </a>
                `;
            });
            
            html += '</div>';
            content.innerHTML = html;
        }
    }

    function getTimeAgo(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }
});
</script>
@endsection