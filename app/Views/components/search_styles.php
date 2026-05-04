<style>
    /* Search Container Styles */
    .search-container {
        position: relative;
        width: 100%;
        max-width: 500px;
    }

    .search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 8px;
        border: 2px solid #334155;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .search-input-wrapper:hover {
        border-color: #475569;
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2);
    }

    .search-input-wrapper:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 8px 12px rgba(0, 0, 0, 0.2);
    }

    .search-input-wrapper .search-icon {
        position: absolute;
        left: 12px;
        color: #94a3b8;
        font-size: 16px;
        pointer-events: none;
    }

    .search-input-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        color: #e2e8f0;
        padding: 10px 12px 10px 38px;
        font-size: 14px;
        outline: none;
    }

    .search-input-wrapper input::placeholder {
        color: #64748b;
    }

    .search-input-wrapper .clear-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 8px 12px;
        cursor: pointer;
        font-size: 16px;
        transition: color 0.2s ease;
        display: none;
    }

    .search-input-wrapper .clear-btn:hover {
        color: #f1f5f9;
    }

    .search-input-wrapper input:not(:placeholder-shown) ~ .clear-btn {
        display: block;
    }

    /* Search Results Panel */
    .search-results-panel {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #1e293b;
        border: 1px solid #334155;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 400px;
        overflow-y: auto;
        display: none;
        z-index: 1000;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    }

    .search-results-panel.show {
        display: block;
    }

    .search-result-item {
        padding: 10px 12px;
        border-bottom: 1px solid #334155;
        cursor: pointer;
        transition: background-color 0.2s ease;
        color: #cbd5e1;
        font-size: 13px;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background-color: #334155;
        color: #f1f5f9;
    }

    .search-result-item .result-code {
        font-weight: 600;
        color: #3b82f6;
    }

    .search-result-item .result-name {
        display: block;
        color: #94a3b8;
        font-size: 12px;
        margin-top: 2px;
    }

    .search-result-item .result-meta {
        display: block;
        color: #64748b;
        font-size: 11px;
        margin-top: 2px;
    }

    .search-no-results {
        padding: 20px 12px;
        text-align: center;
        color: #64748b;
        font-size: 13px;
    }

    .search-loading {
        padding: 10px 12px;
        text-align: center;
        color: #64748b;
        font-size: 13px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .search-container {
            max-width: 100%;
        }
    }
</style>
