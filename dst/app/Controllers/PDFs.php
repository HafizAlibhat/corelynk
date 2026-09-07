<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class PDFs extends BaseController
{
    /**
     * Generate batch report PDF
     */
    public function batchReport($batchId = null)
    {
        $batchModel = new \App\Models\ProcessBatchModel();
        $logModel = new \App\Models\ProcessBatchLogModel();
        
        if ($batchId) {
            // Single batch report
            $batch = $batchModel->select('
                process_batches.*,
                work_orders.work_order_number,
                products.name as product_name,
                products.product_code,
                processes.name as process_name,
                processes.process_code
            ')
            ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
            ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
            ->join('products', 'products.id = work_order_items.product_id', 'left')
            ->join('processes', 'processes.id = process_batches.process_id', 'left')
            ->find($batchId);

            if (!$batch) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Batch not found');
            }

            // Get batch logs
            $logs = $logModel->select('
                process_batch_logs.*,
                employees.name as employee_name
            ')
            ->join('employees', 'employees.id = process_batch_logs.employee_id', 'left')
            ->where('batch_id', $batchId)
            ->orderBy('created_at', 'ASC')
            ->findAll();

            $html = $this->generateBatchReportPDF($batch, $logs);
            $filename = 'batch_report_' . $batch['batch_code'] . '_' . date('Y-m-d') . '.pdf';
        } else {
            // All batches report
            $batches = $batchModel->select('
                process_batches.*,
                work_orders.work_order_number,
                products.name as product_name,
                processes.name as process_name
            ')
            ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
            ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
            ->join('products', 'products.id = work_order_items.product_id', 'left')
            ->join('processes', 'processes.id = process_batches.process_id', 'left')
            ->orderBy('process_batches.created_at', 'DESC')
            ->findAll();

            $html = $this->generateBatchListPDF($batches);
            $filename = 'batch_list_report_' . date('Y-m-d') . '.pdf';
        }

        // Set headers for PDF download
        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        return $html;
    }

    /**
     * Generate work order PDF
     */
    public function workOrderReport($workOrderId)
    {
        $workOrderModel = new \App\Models\WorkOrderModel();
        $batchModel = new \App\Models\ProcessBatchModel();
        
        $workOrder = $workOrderModel->select('
            work_orders.*,
            users.username as created_by_name
        ')
        ->join('users', 'users.id = work_orders.created_by', 'left')
        ->find($workOrderId);

        if (!$workOrder) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Work order not found');
        }

        // Get work order items with batches
        $items = $workOrderModel->getWorkOrderItemsWithBatches($workOrderId);

        $html = $this->generateWorkOrderPDF($workOrder, $items);
        $filename = 'work_order_' . $workOrder['work_order_number'] . '_' . date('Y-m-d') . '.pdf';

        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        return $html;
    }

    /**
     * Generate gate pass PDF
     */
    public function gatePassReport($gatePassId)
    {
        $gatePassModel = new \App\Models\GatePassModel();
        
        $gatePass = $gatePassModel->select('
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.address as vendor_address,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ')
        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
        ->join('users', 'users.id = gate_passes.created_by', 'left')
        ->find($gatePassId);

        if (!$gatePass) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Gate pass not found');
        }

        $items = json_decode($gatePass['items'], true) ?? [];

        $html = $this->generateGatePassPDF($gatePass, $items);
        $filename = 'gate_pass_' . $gatePass['gate_pass_number'] . '.pdf';

        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        return $html;
    }

    /**
     * Generate batch report PDF HTML
     */
    private function generateBatchReportPDF($batch, $logs)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Batch Report - ' . $batch['batch_code'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .company-name { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .report-title { font-size: 18px; color: #666; }
                .info-section { margin-bottom: 25px; }
                .info-table { width: 100%; }
                .info-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .info-label { font-weight: bold; width: 150px; color: #555; }
                .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
                .status-planned { background-color: #6c757d; color: white; }
                .status-in-progress { background-color: #007bff; color: white; }
                .status-completed { background-color: #28a745; color: white; }
                .logs-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .logs-table th, .logs-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .logs-table th { background-color: #f8f9fa; font-weight: bold; }
                .log-type-production { color: #007bff; font-weight: bold; }
                .log-type-quality { color: #28a745; font-weight: bold; }
                .log-type-issue { color: #dc3545; font-weight: bold; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div>
                <div class="report-title">Batch Production Report</div>
                <div style="font-size: 14px; color: #888; margin-top: 10px;">Generated on: ' . date('d/m/Y H:i:s') . '</div>
            </div>

            <div class="info-section">
                <h3 style="color: #333; margin-bottom: 15px;">Batch Information</h3>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Batch Code:</td>
                        <td><strong>' . htmlspecialchars($batch['batch_code']) . '</strong></td>
                        <td class="info-label">Status:</td>
                        <td><span class="status-badge status-' . str_replace('_', '-', $batch['status']) . '">' . ucwords(str_replace('_', ' ', $batch['status'])) . '</span></td>
                    </tr>
                    <tr>
                        <td class="info-label">Work Order:</td>
                        <td>WO-' . str_pad($batch['work_order_id'] ?? 0, 4, '0', STR_PAD_LEFT) . '</td>
                        <td class="info-label">Work Order No:</td>
                        <td>' . htmlspecialchars($batch['work_order_number'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="info-label">Product:</td>
                        <td>' . htmlspecialchars($batch['product_name'] ?? 'N/A') . '</td>
                        <td class="info-label">Product Code:</td>
                        <td>' . htmlspecialchars($batch['product_code'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="info-label">Process:</td>
                        <td>' . htmlspecialchars($batch['process_name'] ?? 'N/A') . '</td>
                        <td class="info-label">Process Code:</td>
                        <td>' . htmlspecialchars($batch['process_code'] ?? 'N/A') . '</td>
                    </tr>
                    <tr>
                        <td class="info-label">Planned Quantity:</td>
                        <td><strong>' . number_format($batch['planned_quantity']) . ' pcs</strong></td>
                        <td class="info-label">Actual Quantity:</td>
                        <td><strong>' . ($batch['actual_quantity'] ? number_format($batch['actual_quantity']) . ' pcs' : 'Not completed') . '</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">Start Date:</td>
                        <td>' . date('d/m/Y H:i', strtotime($batch['start_date'])) . '</td>
                        <td class="info-label">Completion Date:</td>
                        <td>' . ($batch['completion_date'] ? date('d/m/Y H:i', strtotime($batch['completion_date'])) : 'Not completed') . '</td>
                    </tr>
                </table>
            </div>';

        if ($batch['notes']) {
            $html .= '
            <div class="info-section">
                <h3 style="color: #333; margin-bottom: 10px;">Notes</h3>
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">
                    ' . nl2br(htmlspecialchars($batch['notes'])) . '
                </div>
            </div>';
        }

        // Production logs
        if (!empty($logs)) {
            $html .= '
            <div class="info-section">
                <h3 style="color: #333; margin-bottom: 15px;">Production Logs (' . count($logs) . ' entries)</h3>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Employee</th>
                            <th>Log Type</th>
                            <th>Quantities</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($logs as $log) {
                $logTypeClass = 'log-type-' . ($log['log_type'] ?? 'production');
                $quantities = [];
                if (!empty($log['qty_received']) && $log['qty_received'] > 0) $quantities[] = 'Received: ' . $log['qty_received'];
                if (!empty($log['qty_completed']) && $log['qty_completed'] > 0) $quantities[] = 'Completed: ' . $log['qty_completed'];
                if (!empty($log['qty_rejected']) && $log['qty_rejected'] > 0) $quantities[] = 'Rejected: ' . $log['qty_rejected'];
                if (!empty($log['qty_scrapped']) && $log['qty_scrapped'] > 0) $quantities[] = 'Scrapped: ' . $log['qty_scrapped'];
                
                $html .= '
                    <tr>
                        <td>' . date('d/m/Y H:i', strtotime($log['created_at'])) . '</td>
                        <td>' . htmlspecialchars($log['employee_name'] ?? 'System') . '</td>
                        <td><span class="' . $logTypeClass . '">' . ucwords(str_replace('_', ' ', $log['log_type'] ?? 'Production')) . '</span></td>
                        <td>' . implode('<br>', $quantities) . '</td>
                        <td>' . htmlspecialchars($log['notes'] ?? '') . '</td>
                    </tr>';
            }

            $html .= '
                    </tbody>
                </table>
            </div>';
        }

        $html .= '
            <div class="footer">
                <p><strong>Production Management System - Batch Report</strong></p>
                <p>This report contains confidential production information. Handle with care.</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Generate batch list PDF HTML
     */
    private function generateBatchListPDF($batches)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Batch List Report - ' . date('Y-m-d') . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 15px; line-height: 1.3; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
                .company-name { font-size: 22px; font-weight: bold; color: #333; margin-bottom: 8px; }
                .report-title { font-size: 16px; color: #666; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .status-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; }
                .status-planned { background-color: #6c757d; color: white; }
                .status-in-progress { background-color: #007bff; color: white; }
                .status-completed { background-color: #28a745; color: white; }
                .footer { margin-top: 30px; text-align: center; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div>
                <div class="report-title">Batch List Report</div>
                <div style="font-size: 12px; color: #888; margin-top: 8px;">Generated on: ' . date('d/m/Y H:i:s') . ' | Total Batches: ' . count($batches) . '</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Batch Code</th>
                        <th>Work Order</th>
                        <th>Product</th>
                        <th>Process</th>
                        <th>Status</th>
                        <th>Planned Qty</th>
                        <th>Actual Qty</th>
                        <th>Start Date</th>
                        <th>Completion</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($batches as $batch) {
            $statusClass = 'status-' . str_replace('_', '-', $batch['status']);
            $statusLabel = ucwords(str_replace('_', ' ', $batch['status']));
            
            $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($batch['batch_code']) . '</strong></td>
                    <td>' . htmlspecialchars($batch['work_order_number'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($batch['product_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($batch['process_name'] ?? 'N/A') . '</td>
                    <td><span class="status-badge ' . $statusClass . '">' . $statusLabel . '</span></td>
                    <td>' . number_format($batch['planned_quantity']) . '</td>
                    <td>' . ($batch['actual_quantity'] ? number_format($batch['actual_quantity']) : '-') . '</td>
                    <td>' . date('d/m/Y', strtotime($batch['start_date'])) . '</td>
                    <td>' . ($batch['completion_date'] ? date('d/m/Y', strtotime($batch['completion_date'])) : '-') . '</td>
                </tr>';
        }

        $html .= '
                </tbody>
            </table>

            <div class="footer">
                <p><strong>Production Management System - Batch List Report</strong></p>
                <p>This report is automatically generated and contains current production status information.</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Generate work order PDF HTML
     */
    private function generateWorkOrderPDF($workOrder, $items)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Work Order - ' . $workOrder['work_order_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
                .header { text-align: center; border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .company-name { font-size: 26px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .work-order-title { font-size: 20px; color: #007bff; font-weight: bold; }
                .info-section { margin-bottom: 25px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .info-table { width: 100%; }
                .info-table td { padding: 8px; border-bottom: 1px solid #eee; }
                .info-label { font-weight: bold; width: 120px; color: #555; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .items-table th { background-color: #f8f9fa; font-weight: bold; }
                .batch-info { font-size: 12px; color: #666; margin-top: 5px; }
                .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
                .priority-high { color: #dc3545; font-weight: bold; }
                .priority-medium { color: #ffc107; font-weight: bold; }
                .priority-low { color: #28a745; font-weight: bold; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 2px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div>
                <div class="work-order-title">WORK ORDER</div>
                <div style="font-size: 16px; color: #888; margin-top: 10px;">' . htmlspecialchars($workOrder['work_order_number']) . '</div>
            </div>

            <div class="info-section">
                <div class="info-grid">
                    <div>
                        <h3 style="color: #333; margin-bottom: 15px;">Order Details</h3>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Order Number:</td>
                                <td><strong>' . htmlspecialchars($workOrder['work_order_number']) . '</strong></td>
                            </tr>
                            <tr>
                                <td class="info-label">Status:</td>
                                <td><span class="status-badge">' . ucfirst($workOrder['status']) . '</span></td>
                            </tr>
                            <tr>
                                <td class="info-label">Priority:</td>
                                <td><span class="priority-' . $workOrder['priority'] . '">' . ucfirst($workOrder['priority']) . '</span></td>
                            </tr>
                            <tr>
                                <td class="info-label">Created By:</td>
                                <td>' . htmlspecialchars($workOrder['created_by_name'] ?? 'System') . '</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h3 style="color: #333; margin-bottom: 15px;">Schedule</h3>
                        <table class="info-table">
                            <tr>
                                <td class="info-label">Start Date:</td>
                                <td>' . date('d/m/Y', strtotime($workOrder['start_date'])) . '</td>
                            </tr>
                            <tr>
                                <td class="info-label">Due Date:</td>
                                <td>' . date('d/m/Y', strtotime($workOrder['due_date'])) . '</td>
                            </tr>
                            <tr>
                                <td class="info-label">Created:</td>
                                <td>' . date('d/m/Y H:i', strtotime($workOrder['created_at'])) . '</td>
                            </tr>
                            <tr>
                                <td class="info-label">Updated:</td>
                                <td>' . date('d/m/Y H:i', strtotime($workOrder['updated_at'])) . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>';

        if ($workOrder['notes']) {
            $html .= '
            <div class="info-section">
                <h3 style="color: #333; margin-bottom: 10px;">Order Notes</h3>
                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;">
                    ' . nl2br(htmlspecialchars($workOrder['notes'])) . '
                </div>
            </div>';
        }

        // Work order items
        if (!empty($items)) {
            $html .= '
            <h3 style="color: #333; margin-bottom: 15px;">Work Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Completed</th>
                        <th>Progress</th>
                        <th>Batches</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($items as $item) {
                $progress = $item['quantity'] > 0 ? round(($item['completed_quantity'] / $item['quantity']) * 100, 1) : 0;
                
                $html .= '
                    <tr>
                        <td>
                            <strong>' . htmlspecialchars($item['product_name']) . '</strong><br>
                            <small>Code: ' . htmlspecialchars($item['product_code'] ?? 'N/A') . '</small>
                        </td>
                        <td><strong>' . number_format($item['quantity']) . '</strong></td>
                        <td><strong>' . number_format($item['completed_quantity']) . '</strong></td>
                        <td><strong>' . $progress . '%</strong></td>
                        <td>
                            <div class="batch-info">' . ($item['batch_count'] ?? 0) . ' batches created</div>
                        </td>
                    </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        $html .= '
            <div class="footer">
                <p><strong>Production Management System - Work Order</strong></p>
                <p>This document contains confidential production information. Authorized personnel only.</p>
                <p>Generated on: ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Generate gate pass PDF HTML
     */
    private function generateGatePassPDF($gatePass, $items)
    {
        $typeLabel = ucfirst($gatePass['type']) . ' Gate Pass';

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Gate Pass - ' . $gatePass['gate_pass_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                .header { text-align: center; border: 3px solid #333; padding: 20px; margin-bottom: 30px; }
                .company-name { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .gate-pass-title { font-size: 18px; color: #007bff; font-weight: bold; }
                .gate-pass-number { font-size: 20px; color: #dc3545; font-weight: bold; margin-top: 10px; }
                .info-section { margin-bottom: 25px; }
                .info-row { display: flex; margin-bottom: 10px; }
                .info-label { font-weight: bold; width: 150px; color: #555; }
                .info-value { flex: 1; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #333; padding: 10px; text-align: left; }
                .items-table th { background-color: #f0f0f0; font-weight: bold; }
                .status-badge { padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
                .status-pending { background-color: #ffc107; color: #000; }
                .status-approved { background-color: #28a745; color: #fff; }
                .status-completed { background-color: #007bff; color: #fff; }
                .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
                .signature-box { text-align: center; width: 200px; }
                .signature-line { border-bottom: 2px solid #333; height: 50px; margin-bottom: 10px; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; border-top: 2px solid #333; padding-top: 20px; }
                .instructions { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">PRODUCTION MANAGEMENT SYSTEM</div>
                <div class="gate-pass-title">' . $typeLabel . '</div>
                <div class="gate-pass-number">' . $gatePass['gate_pass_number'] . '</div>
            </div>

            <div class="info-section">
                <div class="info-row">
                    <div class="info-label">Date & Time:</div>
                    <div class="info-value"><strong>' . date('d/m/Y H:i', strtotime($gatePass['created_at'])) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value"><span class="status-badge status-' . $gatePass['status'] . '">' . ucfirst($gatePass['status']) . '</span></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value"><strong>' . $typeLabel . '</strong></div>
                </div>
            </div>

            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Vendor Information</h3>
                <div class="info-row">
                    <div class="info-label">Vendor Name:</div>
                    <div class="info-value"><strong>' . htmlspecialchars($gatePass['vendor_name']) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Contact Person:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['contact_person'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_phone'] ?? 'N/A') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['vendor_address'] ?? 'N/A') . '</div>
                </div>
            </div>

            <div class="info-section">
                <h3 style="margin-bottom: 15px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Purpose & Schedule</h3>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><strong>' . htmlspecialchars($gatePass['purpose']) . '</strong></div>
                </div>';

        if ($gatePass['expected_date']) {
            $html .= '
                <div class="info-row">
                    <div class="info-label">Expected Date:</div>
                    <div class="info-value">' . date('d/m/Y H:i', strtotime($gatePass['expected_date'])) . '</div>
                </div>';
        }

        if ($gatePass['notes']) {
            $html .= '
                <div class="info-row">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">' . htmlspecialchars($gatePass['notes']) . '</div>
                </div>';
        }

        $html .= '</div>';

        // Items table
        if (!empty($items)) {
            $html .= '
            <h3 style="color: #333; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Items/Materials</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">S.No</th>
                        <th>Item Description</th>
                        <th style="width: 80px;">Quantity</th>
                        <th style="width: 60px;">Unit</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($items as $index => $item) {
                $html .= '
                    <tr>
                        <td style="text-align: center;"><strong>' . ($index + 1) . '</strong></td>
                        <td>' . htmlspecialchars($item['description'] ?? '') . '</td>
                        <td style="text-align: center;"><strong>' . htmlspecialchars($item['quantity'] ?? '') . '</strong></td>
                        <td style="text-align: center;">' . htmlspecialchars($item['unit'] ?? '') . '</td>
                        <td>' . htmlspecialchars($item['remarks'] ?? '') . '</td>
                    </tr>';
            }

            $html .= '
                </tbody>
            </table>';
        }

        $html .= '
            <div class="instructions">
                <h4 style="margin-top: 0; color: #856404;">Important Instructions:</h4>
                <ul style="margin-bottom: 0;">
                    <li>This gate pass is valid only for the specified date and purpose.</li>
                    <li>All items must be verified by security personnel before entry/exit.</li>
                    <li>Any discrepancy must be reported to management immediately.</li>
                    <li>This document must be presented at the security gate.</li>
                </ul>
            </div>

            <div class="signatures">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Prepared By</strong></div>
                    <div>' . htmlspecialchars($gatePass['created_by_name']) . '</div>
                    <div><small>' . date('d/m/Y', strtotime($gatePass['created_at'])) . '</small></div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Security Guard</strong></div>
                    <div>Name & Signature</div>
                    <div><small>Date: ___________</small></div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div><strong>Authorized By</strong></div>
                    <div>Manager Signature</div>
                    <div><small>Date: ___________</small></div>
                </div>
            </div>

            <div class="footer">
                <p><strong>PRODUCTION MANAGEMENT SYSTEM - GATE PASS</strong></p>
                <p>Original: Security Office | Duplicate: With Vendor | Triplicate: Accounts</p>
                <p>Generated on: ' . date('d/m/Y H:i:s') . ' | System User: ' . session('username') . '</p>
            </div>
        </body>
        </html>';

        return $html;
    }
}
