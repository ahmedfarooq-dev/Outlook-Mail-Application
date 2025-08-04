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

            <!-- Inbox Pagination -->
            <nav id="inbox-pagination" style="display: none;">
                <ul class="pagination justify-content-center">
                    <li class="page-item" id="inbox-prev-page">
                        <a class="page-link" href="#" data-page="prev">Previous</a>
                    </li>
                    <li class="page-item active" id="inbox-current-page">
                        <span class="page-link">1</span>
                    </li>
                    <li class="page-item" id="inbox-next-page">
                        <a class="page-link" href="#" data-page="next">Next</a>
                    </li>
                </ul>
            </nav>
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

            <!-- Sent Pagination -->
            <nav id="sent-pagination" style="display: none;">
                <ul class="pagination justify-content-center">
                    <li class="page-item" id="sent-prev-page">
                        <a class="page-link" href="#" data-page="prev">Previous</a>
                    </li>
                    <li class="page-item active" id="sent-current-page">
                        <span class="page-link">1</span>
                    </li>
                    <li class="page-item" id="sent-next-page">
                        <a class="page-link" href="#" data-page="next">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
    // public/js/outlook-emails.js
document.addEventListener('DOMContentLoaded', function() {
    let inboxCurrentPage = 1;
    let sentCurrentPage = 1;

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
        inboxCurrentPage = 1; // Reset to first page
        loadInbox(true); // Force refresh
    });

    document.getElementById('refresh-sent').addEventListener('click', function() {
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        this.disabled = true;
        sentCurrentPage = 1; // Reset to first page
        loadSent(true); // Force refresh
    });

    // Inbox pagination event listeners
    document.getElementById('inbox-prev-page').addEventListener('click', function(e) {
        e.preventDefault();
        if (inboxCurrentPage > 1) {
            inboxCurrentPage--;
            loadInbox();
        }
    });

    document.getElementById('inbox-next-page').addEventListener('click', function(e) {
        e.preventDefault();
        if (!this.classList.contains('disabled')) {
            inboxCurrentPage++;
            loadInbox();
        }
    });

    // Sent pagination event listeners
    document.getElementById('sent-prev-page').addEventListener('click', function(e) {
        e.preventDefault();
        if (sentCurrentPage > 1) {
            sentCurrentPage--;
            loadSent();
        }
    });

    document.getElementById('sent-next-page').addEventListener('click', function(e) {
        e.preventDefault();
        if (!this.classList.contains('disabled')) {
            sentCurrentPage++;
            loadSent();
        }
    });

    function loadInbox(forceRefresh = false) {
        document.getElementById('inbox-loading').style.display = 'block';
        document.getElementById('inbox-content').style.display = 'none';
        document.getElementById('inbox-pagination').style.display = 'none';

        let url = `/outlook/api/inbox?page=${inboxCurrentPage}`;
        if (forceRefresh) url += '&refresh=true';

        fetch(url)
            .then(response => {
                if (response.status === 401) {
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
                updateInboxPagination(data.currentPage, data.hasMore);
                document.getElementById('inbox-count').textContent = `Page ${data.currentPage}`;
            })
            .catch(error => {
                console.error('Error loading inbox:', error);
                document.getElementById('inbox-content').innerHTML = 
                    '<div class="alert alert-danger">Failed to load inbox emails</div>';
                document.getElementById('inbox-content').style.display = 'block';
            })
            .finally(() => {
                document.getElementById('inbox-loading').style.display = 'none';
                const refreshBtn = document.getElementById('refresh-inbox');
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                refreshBtn.disabled = false;
            });
    }

    function loadSent(forceRefresh = false) {
        document.getElementById('sent-loading').style.display = 'block';
        document.getElementById('sent-content').innerHTML = '';
        document.getElementById('sent-pagination').style.display = 'none';

        let url = `/outlook/api/sent?page=${sentCurrentPage}`;
        if (forceRefresh) url += '&refresh=true';

        fetch(url)
            .then(response => {
                if (response.status === 401) {
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
                updateSentPagination(data.currentPage, data.hasMore);
                document.getElementById('sent-count').textContent = `Page ${data.currentPage}`;
            })
            .catch(error => {
                console.error('Error loading sent items:', error);
                document.getElementById('sent-content').innerHTML = 
                    '<div class="alert alert-danger">Failed to load sent emails</div>';
            })
            .finally(() => {
                document.getElementById('sent-loading').style.display = 'none';
                const refreshBtn = document.getElementById('refresh-sent');
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                refreshBtn.disabled = false;
            });
    }

    function updateInboxPagination(currentPage, hasMore) {
        const pagination = document.getElementById('inbox-pagination');
        const prevBtn = document.getElementById('inbox-prev-page');
        const currentPageSpan = document.getElementById('inbox-current-page');
        const nextBtn = document.getElementById('inbox-next-page');

        currentPageSpan.innerHTML = `<span class="page-link">${currentPage}</span>`;
        
        // Update Previous button
        if (currentPage <= 1) {
            prevBtn.classList.add('disabled');
        } else {
            prevBtn.classList.remove('disabled');
        }

        // Update Next button - disable if no more emails
        if (!hasMore) {
            nextBtn.classList.add('disabled');
        } else {
            nextBtn.classList.remove('disabled');
        }

        pagination.style.display = 'block';
    }

    function updateSentPagination(currentPage, hasMore) {
        const pagination = document.getElementById('sent-pagination');
        const prevBtn = document.getElementById('sent-prev-page');
        const currentPageSpan = document.getElementById('sent-current-page');
        const nextBtn = document.getElementById('sent-next-page');

        currentPageSpan.innerHTML = `<span class="page-link">${currentPage}</span>`;
        
        // Update Previous button
        if (currentPage <= 1) {
            prevBtn.classList.add('disabled');
        } else {
            prevBtn.classList.remove('disabled');
        }

        // Update Next button - disable if no more emails
        if (!hasMore) {
            nextBtn.classList.add('disabled');
        } else {
            nextBtn.classList.remove('disabled');
        }

        pagination.style.display = 'block';
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