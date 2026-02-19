# v1.7-02 Smoke Checklist (Draft/Publish)

## 1) Schema apply

- On PC (Workbench) run `sql/releases/v1.7/schema/schema_02_draft_publish_nullable.sql`.
- Confirm `app_alert_events.published_at` is nullable (YES in SHOW COLUMNS).

## 2) Create draft then filter draft_only

- Open `/admin/alert_ops.php`.
- In "New alert" form leave **published_at** blank and do not check "Publish now". Submit.
- Confirm flash "created". In the list the row has empty published_at (draft).
- Click filter **"Draft only"** (`?draft_only=1`). Confirm only the draft appears.
- Open `/user/alerts.php` (same route_label). Confirm the **draft is not shown** to users.

## 3) Publish then published_only and user visibility

- In alert_ops list click **[Publish]** in the Action column for that draft row.
- Confirm flash "published" and redirect with `?event_id=N` (row highlighted).
- Click filter **"Published only"** (`?published_only=1`). Confirm the row has published_at set.
- Open `/user/alerts.php`. Confirm the **published alert appears** and delivery behavior is unchanged.

## 4) Validation SQL

- On PC run `sql/releases/v1.7/validation/validation_02_draft_publish.sql` (read-only).
- Confirm published_at nullability, draft_cnt / published_cnt, and **0 rows** for content_hash duplicates.
