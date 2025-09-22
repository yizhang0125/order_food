<?php
return '
<style>
:root {
    --primary: #4F46E5;
    --primary-light: #818CF8;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --info: #3B82F6;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
}

.dashboard-header {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.welcome-message {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.date-display {
    color: var(--gray-500);
    font-size: 1.1rem;
    margin-top: 0.5rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.stat-card::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: currentColor;
    opacity: 0.1;
    border-radius: 50%;
    transform: translate(30%, -30%);
    transition: all 0.3s ease;
}

.stat-card:hover::after {
    transform: translate(25%, -25%) scale(1.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.stat-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.stat-change {
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-change.positive { color: var(--success); }
.stat-change.negative { color: var(--danger); }

.recent-orders {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-top: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--gray-200);
}

.orders-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}

.view-all {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.view-all:hover {
    transform: translateX(5px);
    color: var(--primary-light);
}

.order-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}

.order-table th {
    padding: 0.75rem 1rem;
    font-weight: 600;
    color: var(--gray-600);
    background: var(--gray-50);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.order-table td {
    padding: 1rem;
    background: white;
    border-top: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
}

.order-table tr td:first-child {
    border-left: 1px solid var(--gray-200);
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}

.order-table tr td:last-child {
    border-right: 1px solid var(--gray-200);
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
}

.order-id {
    font-weight: 600;
    color: var(--primary);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.processing {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}
</style>';
?>