<style>
    .sc-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 8px 20px 32px;
    }
    @media (min-width: 1200px) {
        .sc-page { padding-left: 28px; padding-right: 28px; }
    }
    .sc-page-heading {
        margin-bottom: 20px;
        padding: 0 4px;
    }
    .sc-page-heading p {
        margin-bottom: 0;
        color: #6b7280;
    }
    .sc-card {
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        overflow: visible;
    }
    .sc-card-body {
        padding: 28px 28px 32px;
    }
    @media (max-width: 768px) {
        .sc-page { padding-left: 12px; padding-right: 12px; }
        .sc-card-body { padding: 20px 16px 24px; }
    }
    .sc-alert {
        padding: 12px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 20px;
    }
    .sc-alert-success {
        color: #047857;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
    }
    .sc-alert-error {
        color: #b91c1c;
        background: #fef2f2;
        border: 1px solid #fecaca;
    }
    .sc-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(280px, 1fr));
        gap: 20px 24px;
        margin-bottom: 24px;
    }
    .sc-form-grid-2 {
        grid-template-columns: repeat(2, minmax(280px, 1fr));
    }
    @media (max-width: 768px) {
        .sc-form-grid, .sc-form-grid-2 { grid-template-columns: 1fr; }
    }
    .sc-field label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #5b21b6;
        background: linear-gradient(135deg, #ede9fe 0%, #f3e8ff 100%);
        padding: 10px 14px;
        border-radius: 10px 10px 0 0;
        margin: 0;
        border: 1px solid #ddd6fe;
        border-bottom: 0;
    }
    .sc-field .sc-control-wrap {
        border: 1px solid #ddd6fe;
        border-top: 0;
        border-radius: 0 0 10px 10px;
        background: #fff;
    }
    .sc-field-nis {
        position: relative;
        z-index: 1;
    }
    .sc-field-nis.sc-dropdown-open {
        z-index: 50;
    }
    .sc-siswa-wrap {
        position: relative;
    }
    .sc-field input[type="text"],
    .sc-field input[readonly] {
        width: 100%;
        height: 44px;
        border: 0;
        padding: 0 14px;
        font-size: 14px;
        background: transparent;
    }
    .sc-control-readonly { background: #f8fafc; }
    .sc-control-readonly input { background: #f8fafc; }
    .sc-control-kartu { background: #fffbeb; }
    .sc-control-kartu input { background: #fffbeb; }
    #siswaAutoList {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 200;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        max-height: 240px;
        overflow: auto;
        box-shadow: 0 12px 32px rgba(0,0,0,.12);
    }
    .sc-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 28px;
        padding-top: 4px;
    }
    .sc-btn {
        min-width: 120px;
        height: 42px;
        padding: 0 22px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        transition: background .15s, border-color .15s;
    }
    .sc-btn:hover { background: #f9fafb; }
    .sc-btn-primary {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    .sc-btn-primary:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
    }
    .sc-btn-sm {
        min-width: auto;
        height: 34px;
        padding: 0 14px;
        font-size: 12px;
    }
    .sc-btn-danger {
        background: #dc2626;
        border-color: #dc2626;
        color: #fff;
    }
    .sc-btn-danger:hover {
        background: #b91c1c;
        border-color: #b91c1c;
    }
    .sc-btn-success {
        background: #059669;
        border-color: #059669;
        color: #fff;
    }
    .sc-btn-success:hover {
        background: #047857;
        border-color: #047857;
    }
    .sc-inline-form {
        margin: 0;
    }
    .sc-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .sc-badge-success {
        color: #047857;
        background: #d1fae5;
    }
    .sc-badge-danger {
        color: #b91c1c;
        background: #fee2e2;
    }
    .sc-table-section {
        margin-top: 8px;
        padding-top: 24px;
        border-top: 1px solid #eef2f7;
    }
    .sc-table-title {
        font-size: 14px;
        font-weight: 800;
        color: #374151;
        margin-bottom: 14px;
        letter-spacing: 0.02em;
    }
    .sc-table-subtitle {
        font-weight: 600;
        color: #6b7280;
        font-size: 13px;
    }
    .sc-pagination-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 14px;
        padding: 4px 2px 0;
    }
    .sc-pagination-info {
        font-size: 13px;
        color: #6b7280;
    }
    .sc-pagination {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    .sc-page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        background: #fff;
        text-decoration: none;
    }
    .sc-page-link:hover:not(.disabled):not(.active) {
        background: #f3f4f6;
    }
    .sc-page-link.active {
        background: #7c3aed;
        border-color: #7c3aed;
        color: #fff;
    }
    .sc-page-link.disabled {
        color: #9ca3af;
        background: #f9fafb;
        cursor: not-allowed;
    }
    .sc-table-wrap {
        overflow: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        max-height: 460px;
        background: #fff;
    }
    .sc-table {
        width: 100%;
        min-width: 520px;
        border-collapse: collapse;
        font-size: 14px;
    }
    .sc-table thead th {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        color: #fff;
        font-weight: 700;
        padding: 12px 16px;
        text-align: left;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .sc-table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
    }
    .sc-table tbody tr:nth-child(even) { background: #f8fafc; }
    .sc-table tbody tr:nth-child(odd) { background: #fff; }
    .sc-table tbody tr:hover { background: #f5f3ff; }
    .sc-empty {
        text-align: center;
        color: #6b7280;
        padding: 32px 16px !important;
    }
</style>
