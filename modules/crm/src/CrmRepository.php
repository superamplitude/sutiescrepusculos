<?php
declare(strict_types=1);

namespace Crepusculo\CRM;

use PDO;
use RuntimeException;

final class CrmRepository
{
    public function __construct(private PDO $db) {}

    private function companyId(int $companyId): int
    {
        if ($companyId < 1) {
            throw new RuntimeException('COMPANY_ID_REQUIRED');
        }
        return $companyId;
    }

    public function dashboard(int $companyId): array
    {
        $companyId = $this->companyId($companyId);
        $queries = [
            'contacts' => "SELECT COUNT(*) FROM crm_contacts WHERE company_id=?",
            'open_leads' => "SELECT COUNT(*) FROM crm_leads WHERE company_id=? AND status='open'",
            'pending_tasks' => "SELECT COUNT(*) FROM crm_tasks WHERE company_id=? AND status='pending'",
            'overdue_tasks' => "SELECT COUNT(*) FROM crm_tasks WHERE company_id=? AND status='pending' AND due_at IS NOT NULL AND due_at<=NOW()",
            'open_conversations' => "SELECT COUNT(*) FROM crm_conversations WHERE company_id=? AND status='open'"
        ];
        $out = [];
        foreach ($queries as $key => $sql) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$companyId]);
            $out[$key] = (int)$stmt->fetchColumn();
        }
        return $out;
    }

    public function contacts(int $companyId, int $limit = 100): array
    {
        $companyId = $this->companyId($companyId);
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare("SELECT id,name,phone,email,is_client,client_since,updated_at FROM crm_contacts WHERE company_id=? ORDER BY updated_at DESC,id DESC LIMIT {$limit}");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leads(int $companyId, ?string $status = null): array
    {
        $companyId = $this->companyId($companyId);
        $sql = "SELECT l.*,c.name contact_name,c.phone,c.email FROM crm_leads l JOIN crm_contacts c ON c.id=l.contact_id AND c.company_id=l.company_id WHERE l.company_id=?";
        $args = [$companyId];
        if ($status !== null && $status !== '') {
            $sql .= " AND l.status=?";
            $args[] = $status;
        }
        $sql .= " ORDER BY l.updated_at DESC,l.id DESC LIMIT 250";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tasks(int $companyId, ?string $status = null): array
    {
        $companyId = $this->companyId($companyId);
        $sql = "SELECT t.*,c.name contact_name,l.title lead_title FROM crm_tasks t LEFT JOIN crm_contacts c ON c.id=t.contact_id AND c.company_id=t.company_id LEFT JOIN crm_leads l ON l.id=t.lead_id AND l.company_id=t.company_id WHERE t.company_id=?";
        $args = [$companyId];
        if ($status !== null && $status !== '') {
            $sql .= " AND t.status=?";
            $args[] = $status;
        }
        $sql .= " ORDER BY (t.status='pending') DESC,t.due_at IS NULL,t.due_at ASC,t.updated_at DESC LIMIT 250";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createContact(int $companyId, array $data): int
    {
        $companyId = $this->companyId($companyId);
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        if ($name === '' || ($phone === '' && $email === '')) {
            throw new RuntimeException('CONTACT_NAME_AND_CHANNEL_REQUIRED');
        }
        $stmt = $this->db->prepare("INSERT INTO crm_contacts(company_id,channel,external_id,name,phone,email,is_client,client_since,notes) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $companyId,
            trim((string)($data['channel'] ?? 'direct')) ?: 'direct',
            ($data['external_id'] ?? null) ?: null,
            $name,
            $phone ?: null,
            $email ?: null,
            !empty($data['is_client']) ? 1 : 0,
            !empty($data['is_client']) ? date('Y-m-d H:i:s') : null,
            ($data['notes'] ?? null) ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createLead(int $companyId, array $data): int
    {
        $companyId = $this->companyId($companyId);
        $contactId = (int)($data['contact_id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        if ($contactId < 1 || $title === '') throw new RuntimeException('LEAD_CONTACT_AND_TITLE_REQUIRED');
        $stmt = $this->db->prepare("INSERT INTO crm_leads(company_id,contact_id,conversation_id,owner_user_id,title,stage,status,source,value,currency,expected_checkin,expected_checkout,guests) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $companyId,$contactId,($data['conversation_id']??null)?:null,($data['owner_user_id']??null)?:null,$title,
            trim((string)($data['stage']??'new')) ?: 'new','open',($data['source']??null)?:null,
            isset($data['value']) ? (float)$data['value'] : null,
            strtoupper(trim((string)($data['currency']??'BRL'))) ?: 'BRL',
            ($data['expected_checkin']??null)?:null,($data['expected_checkout']??null)?:null,
            isset($data['guests']) ? max(1,(int)$data['guests']) : null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function createTask(int $companyId, array $data): int
    {
        $companyId = $this->companyId($companyId);
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new RuntimeException('TASK_TITLE_REQUIRED');
        $stmt = $this->db->prepare("INSERT INTO crm_tasks(company_id,lead_id,contact_id,assigned_user_id,title,description,due_at,priority,status) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $companyId,($data['lead_id']??null)?:null,($data['contact_id']??null)?:null,($data['assigned_user_id']??null)?:null,
            $title,($data['description']??null)?:null,($data['due_at']??null)?:null,
            trim((string)($data['priority']??'normal')) ?: 'normal','pending'
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteLead(int $companyId, int $leadId): void
    {
        $companyId = $this->companyId($companyId);
        $stmt = $this->db->prepare("DELETE FROM crm_leads WHERE id=? AND company_id=?");
        $stmt->execute([$leadId,$companyId]);
    }

    public function deleteTask(int $companyId, int $taskId): void
    {
        $companyId = $this->companyId($companyId);
        $stmt = $this->db->prepare("DELETE FROM crm_tasks WHERE id=? AND company_id=?");
        $stmt->execute([$taskId,$companyId]);
    }
}
