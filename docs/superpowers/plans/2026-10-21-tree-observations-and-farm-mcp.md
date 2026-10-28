# Tree Observations + Farm MCP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** เพิ่มบันทึกสภาพต้น (ปัญหา/ระยะการเติบโต + การติดตามดูซ้ำ + รูป) และเปิด MCP server ให้ Claude อ่าน/เขียนข้อมูลสวนได้ผ่าน OAuth

**Architecture:** Domain rule ที่ต้องเหมือนกันทั้งแอปและ MCP (เปิด/ต่อปัญหา, ดูซ้ำ +7 วัน, ผูกการดูแลกับปัญหา) อยู่ใน action class ที่ `app/Actions/Farm/` — controller และ MCP tool เรียก action เดียวกัน ส่วน shape validation อยู่ที่ entry point ของแต่ละฝั่ง ที่มาของบันทึก (app/mcp) ส่งผ่าน `Illuminate\Support\Facades\Context` ซึ่ง middleware ของ route MCP ตั้งค่าไว้ และ trait ของ model อ่านไปเขียนลงคอลัมน์

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind v4, Wayfinder, Pest 4, `laravel/mcp`, `laravel/passport`, `league/flysystem-aws-s3-v3` (Cloudflare R2)

**Spec:** `docs/superpowers/specs/2026-10-21-tree-observations-design.md`, `docs/superpowers/specs/2026-10-21-farm-mcp-design.md` (ระยะ A+B เท่านั้น — ระยะ C คลังยา/กฎความปลอดภัย และ D แผนงาน ไม่อยู่ในแผนนี้)

## Lean cuts จาก spec (ผู้ใช้ต้องยืนยันก่อนเริ่ม)

ผู้ใช้บอกว่าหลังกรอกข้อมูลตั้งต้นแล้ว จะบันทึกผ่าน AI เป็นหลัก แผนนี้จึงตัด UI ในแอปที่ AI ทำแทนได้:

1. **บันทึกสภาพต้นหลายต้นพร้อมกัน → ทำผ่าน MCP เท่านั้น** ในแอปมีฟอร์มรายต้นที่หน้าต้น (เปิดจาก QR) อย่างเดียว
2. **ไม่มีหน้าจัดการรายการสิ่งที่พบ (เปลี่ยนชื่อ/ซ่อน)** ถ้าพิมพ์ชื่อใหม่ ระบบจะสร้างให้อัตโนมัติ ส่วนการเปลี่ยนชื่อหรือซ่อนเลื่อนไปทำทีหลัง
3. **โน้ตความรู้ของสวนใช้ผ่าน MCP เท่านั้น** ยังไม่มีหน้าในแอป
4. **กู้คืนสิ่งที่ลบผ่าน MCP เท่านั้น** (เครื่องมือ `restore_record`) ยังไม่มีหน้าถังขยะในแอป
5. **รูปไม่ย่อขนาด** จำกัดไว้ 3 รูปต่อบันทึก ไฟล์ละไม่เกิน 8 MB
6. **ป้าย "AI" แสดงเฉพาะในไทม์ไลน์ของหน้าต้น**
7. **แก้บันทึกสภาพต้นได้เฉพาะ วันที่ ความรุนแรง และหมายเหตุ** ถ้าต้องการเปลี่ยนสิ่งที่พบหรือต้น ให้ลบแล้วบันทึกใหม่

## Global Constraints

- ข้อความที่ผู้ใช้เห็นทั้งหมดเป็นภาษาไทย รวมถึง `aria-label`, `sr-only` และ validation message ส่วนชื่อในโค้ด รวมถึงชื่อ, description และ schema ของ MCP tool เป็นภาษาอังกฤษ
- ทำตาม convention เดิม: `#[Fillable]` attribute, `casts()` method, PHPDoc `@property`, `Inertia::flash('toast', ['type' => 'success', 'message' => ...])`, Pest แบบ `test()` ที่มี `beforeEach` สร้าง `$this->user`
- Dependency ที่อนุมัติแล้ว (ห้ามเพิ่มตัวอื่น): `laravel/mcp` (ย้ายเข้า `require`), `laravel/passport`, `league/flysystem-aws-s3-v3`
- วันดูซ้ำเริ่มต้น = วันที่อ้างอิง + **7** วัน (ค่าคงที่ `TreeIssue::FOLLOW_UP_DAYS = 7`)
- ความรุนแรงมี 3 ระดับ: `low` น้อย, `medium` กลาง, `high` มาก (ไม่บังคับกรอก)
- รูป: เก็บใน disk ชื่อ `photos`, สูงสุด 3 รูปต่อบันทึก, ไฟล์ละไม่เกิน 8192 KB, แสดงผลด้วย `temporaryUrl` อายุ 30 นาที
- Soft delete ใช้กับ: `care_records`, `harvests`, `sales`, `expenses`, `observations`, `tree_issues`, `farm_notes` เท่านั้น
- ทุกงานต้องจบด้วย `vendor/bin/pint --dirty --format agent` และ `php artisan test --compact` ของไฟล์ที่เกี่ยวข้อง ถ้าแก้ frontend ต้องรัน `npm run types && npm run lint` ด้วย (ตรวจชื่อ script จริงใน `package.json` ก่อน)
- ก่อนเริ่ม Task 1: working tree บน `main` มีงาน UI ที่ยังไม่ได้ commit อยู่ ให้ผู้ใช้ commit หรือ stash ก่อน แล้วค่อยแตก branch `feature/tree-observations-mcp`

## Review Focus

1. **บันทึกปัญหาเดิมซ้ำที่ต้นเดิม** (ทั้งจากแอปและจาก MCP) ต้องต่อเข้าเรื่องเดิม ห้ามเปิดเรื่องใหม่ → test อยู่ใน Task 3
2. **ต้นที่ถูก remove แล้ว** ต้องบันทึกสภาพต้นไม่ได้ และต้องไม่แสดงในรายการ "ต้องดูซ้ำ" → test อยู่ใน Task 3 และ Task 5
3. **ผลรวมหลัง soft delete:** ผลผลิตที่ลบแล้วต้องไม่ถูกนับในยอดของสวนและของฤดูกาล → test อยู่ใน Task 1
4. **ลบการดูแลที่ผูกกับปัญหาไว้:** ปัญหายังเปิดอยู่ และวันดูซ้ำไม่เปลี่ยน → test อยู่ใน Task 4
5. **MCP อ้างรหัสต้นโดยไม่ระบุสวน** ขณะที่มีมากกว่า 1 สวน ต้องได้ error ที่บอกชัดว่าให้ระบุ `orchard_id` → test อยู่ใน Task 9

---

### Task 1: ที่มาของบันทึก + soft delete บนบันทึกเดิม

**Files:**
- Create: `app/Models/Concerns/TracksProvenance.php`, `app/Support/Provenance.php`, migration `add_provenance_and_soft_deletes_to_farm_records`
- Modify: `app/Models/CareRecord.php`, `Harvest.php`, `Sale.php`, `Expense.php`
- Test: `tests/Feature/Farm/ProvenanceTest.php`

**Interfaces:**
- Produces:
  - `Provenance::columns(Blueprint $table): void` เพิ่มคอลัมน์ `created_by`/`updated_by` (FK users, nullable, `nullOnDelete`) และ `created_via`/`updated_via` (string(10), default `'app'`)
  - `Provenance::VIA_KEY = 'record_via'` เป็น key ใน Context, ค่าที่ใช้คือ `'app'` หรือ `'mcp'`
  - trait `TracksProvenance` ตั้งค่าตอน `creating` (ทั้ง 4 คอลัมน์) และตอน `updating` (`updated_*`) จาก `auth()->id()` กับ `Context::get(Provenance::VIA_KEY, 'app')` และมี method `isFromAi(): bool` (true เมื่อ `created_via` หรือ `updated_via` เป็น `'mcp'`)
  - ทั้ง 4 model ใช้ `SoftDeletes` + `TracksProvenance`

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('records created in app store creator and via app')`: actingAs user แล้ว POST harvest ผ่าน `trees.harvests.store` → `created_by` = user id, `created_via` = `'app'`
  - `test('records created under mcp context store via mcp')`: `Context::add('record_via', 'mcp')` แล้ว `Harvest::factory()->create()` → `created_via` = `'mcp'` และ `isFromAi()` = true
  - `test('updating sets updated_by and updated_via only')`: สร้างใน context app แล้ว update ใน context mcp → `created_via` = `'app'`, `updated_via` = `'mcp'`
  - `test('deleting a harvest soft deletes it')`: DELETE `harvests.destroy` → `assertSoftDeleted`
  - `test('soft deleted harvests are excluded from orchard and season yields')`: harvest 10 กก. + harvest 5 กก. ที่ลบแล้ว อยู่ในฤดูกาลที่เปิดอยู่ → `orchards.index` prop `orchards.0.current_yield_kg` = 10 และ `trees.show` prop `seasonYields.0.weightKg` = 10
- [x] **Step 2:** รัน `php artisan test --compact tests/Feature/Farm/ProvenanceTest.php` → ต้อง FAIL
- [x] **Step 3:** สร้าง migration ที่เพิ่ม `softDeletes()` และ `Provenance::columns()` ให้ทั้ง 4 ตาราง แล้วเขียน trait และเพิ่ม trait ใน model พร้อมอัปเดต `@property` PHPDoc
- [x] **Step 4:** รัน test ไฟล์นี้ และ `tests/Feature/Farm` ทั้งโฟลเดอร์ → ต้อง PASS ทั้งหมด (test เดิมที่เช็ก `assertDatabaseMissing` หลังลบ ให้เปลี่ยนเป็น `assertSoftDeleted`)
- [x] **Step 5:** Commit `feat: track record provenance and soft delete farm records`

### Task 2: Schema และ model ของสิ่งที่พบ, ปัญหา, การสังเกต

**Files:**
- Create: `app/Enums/FindingKind.php`, `Severity.php`, `FollowUpOutcome.php`; `app/Models/Finding.php`, `TreeIssue.php`, `Observation.php`, `ObservationPhoto.php`; migrations; factories ของ 4 model
- Modify: `app/Models/Tree.php`, `app/Models/CareRecord.php`
- Test: `tests/Feature/Farm/ObservationModelTest.php`

**Interfaces:**
- Produces:
  - Enum (มี `label(): string` ภาษาไทยเหมือน `CareType`):
    - `FindingKind`: `Problem='problem'` ปัญหา, `Stage='stage'` ระยะการเติบโต
    - `Severity`: `Low='low'`, `Medium='medium'`, `High='high'`
    - `FollowUpOutcome`: `Resolved='resolved'` หายแล้ว, `NotResolved='not_resolved'` ยังไม่หาย
  - `findings`: `id, name (unique), kind, timestamps`
    - migration insert ค่าเริ่มต้น เพื่อให้ production มีข้อมูลด้วย
    - ปัญหา: ไรแดง, เพลี้ยไฟ, เพลี้ยไก่แจ้ง, หนอนเจาะผล, ใบไหม้, ไฟทอปธอร่า/รากเน่าโคนเน่า, ราสีชมพู, ใบติด
    - ระยะ: แตกใบอ่อน, ออกดอก, ดอกบาน, ติดผล, ผลดก, ผลร่วง
  - `tree_issues`: `id, tree_id (cascade), finding_id (restrict), opened_on, follow_up_on, resolved_on nullable, timestamps, softDeletes, Provenance::columns`
  - `observations`: `id, tree_id (cascade), finding_id (restrict), tree_issue_id nullable (cascade), observed_on, severity nullable, outcome nullable, notes nullable, timestamps, softDeletes, Provenance::columns` (follow-up คือ observation ที่มี `outcome`)
  - `observation_photos`: `id, observation_id (cascade), path, timestamps`
  - `care_record_tree_issue`: `care_record_id, tree_issue_id` (cascade ทั้งสองฝั่ง) unique คู่
  - `Finding::resolveByName(string $name, FindingKind $kind): Finding` ใช้ `firstOrCreate` โดย trim ชื่อก่อน
  - `TreeIssue::FOLLOW_UP_DAYS = 7`, `scopeOpen()` (`resolved_on` เป็น null), `scopeDue(Builder $q, CarbonInterface $today)` (open + `follow_up_on <= today` + ต้นยังไม่ถูก remove), relations `tree()`, `finding()`, `observations()`, `careRecords()`
  - `Tree::issues(): HasMany`, `Tree::observations(): HasMany`, `CareRecord::issues(): BelongsToMany` (`care_record_tree_issue`)
  - `Observation::photos(): HasMany`, `issue(): BelongsTo`
  - `Tree::hasRecords()` นับ observations ด้วย
  - `TreeIssue` กับ `Observation` ใช้ `SoftDeletes` + `TracksProvenance` จาก Task 1

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('default findings are seeded by migration')`: `Finding::where('name','ไรแดง')->first()->kind` = `FindingKind::Problem` และ `Finding::count()` = 14
  - `test('resolveByName reuses existing finding and creates new ones')`: เรียก `'  ไรแดง '` แล้วได้ id เดิม เรียก `'ราดำ'` แล้ว count เพิ่ม 1
  - `test('due scope returns open issues due today or overdue on active trees')`: มี 4 issue คือ ครบกำหนดวันนี้, เลยกำหนดแล้ว, ยังไม่ครบกำหนด และปิดแล้ว อีก 1 issue อยู่บนต้นที่ถูก remove → ได้ 2 รายการ
  - `test('tree with observations cannot be hard deleted')`: DELETE `trees.destroy` → มี error `tree`
- [x] **Step 2:** รัน `php artisan test --compact tests/Feature/Farm/ObservationModelTest.php` → ต้อง FAIL
- [x] **Step 3:** สร้าง enum, migration, model และ factory ทั้งหมด (`php artisan make:model -mf`)
- [x] **Step 4:** รัน test ไฟล์นี้ และ `TreeControllerTest` → ต้อง PASS
- [x] **Step 5:** Commit `feat: add findings, tree issues and observations schema`

### Task 3: Action บันทึกสภาพต้น/ดูซ้ำ + รูปบน R2 + route ฝั่งแอป

**Files:**
- Create: `app/Actions/Farm/RecordObservation.php`, `RecordFollowUp.php`; `app/Http/Controllers/Farm/ObservationController.php`; `app/Http/Requests/Farm/StoreObservationRequest.php`, `StoreFollowUpRequest.php`, `UpdateObservationRequest.php`
- Modify: `routes/web.php`, `config/filesystems.php` (disk `photos`), `.env.example`, `composer.json` (เพิ่ม `league/flysystem-aws-s3-v3`)
- Test: `tests/Feature/Farm/ObservationControllerTest.php`

**Interfaces:**
- Consumes: model และ enum จาก Task 2
- Produces:
  - `RecordObservation::handle(Tree $tree, Finding $finding, array $data, array $photos = []): Observation`
    - `$data` มี: `observed_on`, `severity?`, `notes?`, `follow_up_on?`
    - `$photos` เป็น `UploadedFile[]`
    - throw `ValidationException` key `tree` ถ้าต้นถูก remove แล้ว
    - ถ้า finding เป็น Problem: หา issue ที่เปิดอยู่ของ (tree, finding) ถ้าไม่มีให้สร้างใหม่ (`opened_on` = observed_on, `follow_up_on` = `follow_up_on` ที่ส่งมา ถ้าไม่ส่งใช้ observed_on + 7) ถ้ามีอยู่แล้วให้ต่อเข้าเรื่องเดิม และเปลี่ยน `follow_up_on` เฉพาะเมื่อส่งค่ามา จากนั้นตั้ง `tree_issue_id`
    - ถ้าเป็น Stage: ไม่มี issue
    - รูปเก็บที่ `photos` disk path `observations/{observation_id}/`
  - `RecordFollowUp::handle(TreeIssue $issue, array $data, array $photos = []): Observation`
    - `$data` มี: `observed_on`, `outcome`, `notes?`, `next_follow_up_on?`
    - สร้าง observation (finding เดียวกับ issue พร้อม `outcome`)
    - ถ้า Resolved: `resolved_on` = observed_on
    - ถ้า NotResolved: `follow_up_on` = next ?? observed_on + 7
    - throw ถ้า issue ปิดไปแล้ว
  - Routes:
    - `POST trees/{tree}/observations` (`trees.observations.store`, body: `finding_id` หรือ `finding_name` + `kind`, `observed_on`, `severity`, `notes`, `follow_up_on`, `photos[]`)
    - `POST tree-issues/{tree_issue}/follow-ups` (`tree-issues.follow-ups.store`)
    - `PATCH observations/{observation}` (`observations.update`: แก้ได้เฉพาะ observed_on/severity/notes)
    - `DELETE observations/{observation}` (`observations.destroy`)
    - ทุก route redirect ไป `trees.show`
  - disk `photos`: driver อ่านจาก `PHOTOS_DISK_DRIVER` (default `local`, root `storage_path('app/private/photos')`, `serve => true`) และค่า R2 จาก `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `region` = `auto`, `use_path_style_endpoint` = true

- [x] **Step 1: เขียน test ที่ต้อง fail** (`Storage::fake('photos')`, `Date::setTestNow('2026-10-21')`)
  - `test('problem observation opens an issue with follow up seven days later')`: ไรแดง วันที่ 2026-10-21 → `tree_issues` 1 แถว, `follow_up_on` = `2026-10-28`
  - `test('same problem on same tree appends to the open issue')`: บันทึกไรแดงซ้ำ → issue ยังมี 1 แถว, observations 2 แถว, `follow_up_on` เท่าเดิม
  - `test('stage observation does not open an issue')`: ออกดอก → `tree_issues` 0 แถว
  - `test('new finding name is created')`: `finding_name` = `ราดำ`, `kind` = `problem` → `findings` มีแถวนี้
  - `test('removed tree rejects observations')`: ต้นที่ remove แล้ว → session error `tree`
  - `test('photos are stored on photos disk')`: แนบ 2 รูป → `observation_photos` 2 แถว และไฟล์มีอยู่จริง ถ้าแนบรูปที่ 4 → error `photos`
  - `test('follow up resolved closes the issue')` และ `test('follow up not resolved pushes follow up date')`: วันที่ 2026-10-28 → `follow_up_on` = `2026-11-04`
  - `test('follow up on closed issue is rejected')`
  - `test('update changes only date severity and notes')` และ `test('destroy soft deletes observation')`
- [x] **Step 2:** รัน `php artisan test --compact tests/Feature/Farm/ObservationControllerTest.php` → ต้อง FAIL
- [x] **Step 3:** `composer require league/flysystem-aws-s3-v3` แล้วเพิ่ม disk, action, request, controller และ route ตาม Interfaces
- [x] **Step 4:** รัน test → ต้อง PASS
- [x] **Step 5:** Commit `feat: record tree observations and follow-ups`

### Task 4: Action บันทึกการดูแลที่ผูกกับปัญหา

**Files:**
- Create: `app/Actions/Farm/SaveCareRecord.php`
- Modify: `app/Http/Controllers/Farm/CareRecordController.php`, `app/Http/Requests/Farm/StoreCareRecordRequest.php` (เพิ่ม `issue_ids`)
- Test: `tests/Feature/Farm/CareRecordIssueLinkTest.php`

**Interfaces:**
- Consumes: `TreeIssue`, `CareRecord::issues()` จาก Task 2
- Produces:
  - `SaveCareRecord::handle(Orchard $orchard, array $data, ?CareRecord $record = null): CareRecord`
    - `$data` เป็น validated fields + `tree_ids` + `issue_ids?`
    - ทำทั้งหมดใน transaction: sync trees, sync issues
    - issue ที่ผูกได้ต้องเป็นของต้นใน `tree_ids` และต้องยังเปิดอยู่ หรือเคยผูกไว้แล้ว ถ้าไม่ใช่ throw `ValidationException` key `issue_ids`
    - issue ที่ **เพิ่งผูกใหม่**: `follow_up_on` = performed_on + 7
    - issue ที่ถูกเอาออก: วันดูซ้ำไม่เปลี่ยน
  - `SaveCareRecord::defaultIssueIds(CareType $type, array $treeIds): array` คืน id ของ issue ที่เปิดอยู่บนต้นเหล่านั้น ถ้าเป็น `Pesticide` ถ้าไม่ใช่คืน `[]` (MCP ใช้เมื่อ AI ไม่ส่ง `issue_ids` มา และ UI ใช้ตัดสินว่าจะติ๊กไว้ให้หรือไม่)
  - `CareRecordController::store/update` เรียก action ส่วน `issue_ids` ที่ไม่ได้ส่งมาจากฟอร์มถือว่าเป็น `[]` (UI ส่งค่าที่ผู้ใช้เลือกมาเสมอ)

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('linking an issue moves its follow up to care date plus seven')`: issue follow_up 2026-10-28 + พ่นยาวันที่ 2026-10-25 → `2026-11-01`
  - `test('issue from a tree not on the record is rejected')`
  - `test('deleting a linked care record keeps the issue open and follow up unchanged')`
  - `test('removing a tree from a record unlinks its issues')`
  - `test('default issue ids are open issues for pesticide only')`: pesticide ได้ ids, fertilizer ได้ `[]`
  - test เดิมใน `CareRecordControllerTest.php` ต้องยังผ่าน
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** ย้าย logic จาก controller เข้า action ตาม Interfaces
- [x] **Step 4:** รัน `CareRecordIssueLinkTest.php` + `CareRecordControllerTest.php` → ต้อง PASS
- [x] **Step 5:** Commit `feat: link care records to tree issues`

### Task 5: Props ของหน้าต้น/หน้าสวน + ไทม์ไลน์รวมการสังเกต

**Files:**
- Create: `app/Support/TreeTimeline.php`, `app/Support/IssueSummary.php`
- Modify: `TreeController.php` (ย้าย `timeline()` ออกไป), `OrchardController::show`, `CareRecordController::create`
- Test: `tests/Feature/Farm/TreeObservationPropsTest.php`

**Interfaces:**
- Produces:
  - `TreeTimeline::for(Tree $tree, int $limit = 50): array`
    - `kind` เพิ่ม `'observation'` (title = ชื่อสิ่งที่พบ หรือ "ดูซ้ำ: <ชื่อ> — หายแล้ว/ยังไม่หาย", detail = ความรุนแรง · หมายเหตุ)
    - ทุก entry มี `fromAi: bool`
    - observation entry มี `photos: array<int, string>` เป็น temporary URL ของ disk `photos` อายุ 30 นาที
  - `IssueSummary::forIssue(TreeIssue $issue): array{id:int, treeId:int, treeCode:string, finding:string, openedOn:string, followUpOn:string, isDue:bool}`
  - Props ของ `trees.show` (เพิ่ม):
    - `openIssues: IssueSummary[]`
    - `latestStage: array{finding:string, observedOn:string, inCurrentSeason:bool}|null`
    - `findings: array<int, array{id:int, name:string, kind:string}>`
  - Props ของ `orchards.show` (เพิ่ม):
    - `dueIssues: IssueSummary[]` (scope `due` เรียงตาม follow_up_on)
    - `openIssues: IssueSummary[]` (ปัญหาที่เปิดอยู่ทั้งหมดบนต้นที่ยังอยู่)
  - Props ของ `care-records.create` และหน้าที่ใช้ `CareRecordFields`: `openIssues: IssueSummary[]` ของสวนนั้น

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('tree page exposes open issues latest stage and findings')`
  - `test('latest stage outside current season is flagged')`: ระยะของปีก่อน → `inCurrentSeason` = false
  - `test('timeline includes observations with ai flag and photo urls')`
  - `test('orchard page lists due issues and excludes removed trees')`
  - `test('care record create page exposes open issues')`
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** เขียน `TreeTimeline` / `IssueSummary` แล้วต่อเข้ากับ controller
- [x] **Step 4:** รัน test ไฟล์นี้ + `TreeControllerTest` + `OrchardControllerTest` → ต้อง PASS
- [x] **Step 5:** Commit `feat: expose tree observations and due follow-ups to pages`

### Task 6: UI — บันทึกสภาพต้น, ดูซ้ำ, รายการต้องดูซ้ำ, ผูกปัญหาในฟอร์มดูแล

**Files:**
- Create: `resources/js/components/farm/observation-form.tsx`, `follow-up-form.tsx`, `issue-list.tsx`
- Modify: `resources/js/types/farm.ts`, `pages/farm/trees/show.tsx`, `pages/farm/orchards/show.tsx`, `components/farm/care-record-form.tsx`, `pages/farm/care-records/create.tsx`
- Test: ใช้ prop test จาก Task 5 + `npm run types`, `npm run lint`, `npm run build`

**Interfaces:**
- Consumes: props จาก Task 5 และ route จาก Task 3 (import ผ่าน Wayfinder `@/routes/...`)
- Produces (ข้อความที่ใช้บนหน้าจอ):
  - หน้าต้น:
    - ปุ่ม "บันทึกสภาพต้น" เปิด dialog `ObservationForm`
      - ปุ่มสลับ "ปัญหา" / "ระยะการเติบโต"
      - chip ของ findings ตามประเภทที่เลือก + ช่อง "อื่น ๆ (พิมพ์ชื่อ)"
      - chip ความรุนแรง "น้อย/กลาง/มาก" (เฉพาะปัญหา และกดซ้ำเพื่อยกเลิกได้)
      - วันดูซ้ำ (เฉพาะปัญหา ค่าเริ่มต้น +7 วัน)
      - หมายเหตุ
      - รูป `accept="image/*" capture="environment" multiple`
    - การ์ด "ปัญหาที่ยังไม่จบ" อยู่บนสุด แต่ละรายการมีปุ่ม "ดูซ้ำ" ที่เปิด `FollowUpForm` (ปุ่ม "หายแล้ว" / "ยังไม่หาย" + วันดูซ้ำครั้งถัดไป เมื่อเลือกยังไม่หาย + หมายเหตุ + รูป)
    - แสดง "ระยะล่าสุด: <ชื่อ> · <วันที่>" ถ้า `inCurrentSeason` = false ให้ใช้ `text-muted-foreground`
    - ไทม์ไลน์แสดง observation, รูปย่อ และ badge "AI" เมื่อ `fromAi` เป็น true
  - หน้าสวน: `IssueList` สองกลุ่มบนแท็บต้นไม้ คือ "ต้องดูซ้ำ" (ไฮไลต์รายการที่เลยกำหนด) และ "ปัญหาค้างอยู่" แต่ละรายการลิงก์ไปหน้าต้น ถ้าว่างทั้งคู่ให้ซ่อน
  - ฟอร์มดูแล: เมื่อเลือกต้นที่มีปัญหาเปิดอยู่ ให้แสดง checkbox "ผูกกับปัญหา" ต่อปัญหา ถ้าประเภทเป็น `pesticide` ให้ติ๊กไว้ให้ ถ้าเป็นประเภทอื่นไม่ติ๊ก (ติ๊กตามค่าเริ่มต้นเฉพาะตอนสร้าง ตอนแก้ให้ติ๊กตามที่ผูกไว้เดิม) แล้วส่งเป็น `issue_ids[]`

- [x] **Step 1:** เพิ่ม type ใน `farm.ts`: `Finding`, `FindingKind`, `IssueSummary`, `LatestStage` และเพิ่ม kind `'observation'` ใน `TimelineEntry` พร้อม `fromAi`/`photos`
- [x] **Step 2:** สร้าง component 3 ตัว แล้วต่อเข้ากับหน้า โดยใช้ `Dialog`, `Button` และ `SectionTabs` ที่มีอยู่ และทำตาม `DESIGN.md`
- [x] **Step 3:** รัน `npm run types && npm run lint && npm run build` → ต้องไม่มี error
- [x] **Step 4:** รัน `php artisan test --compact tests/Feature/Farm` → ต้อง PASS
- [x] **Step 5:** Commit `feat: tree observation and follow-up UI`

### Task 7: Passport + MCP server + ล็อกอิน + เครื่องมือภาพรวม

**Files:**
- Create: `routes/ai.php`, `app/Mcp/Servers/FarmServer.php`, `app/Mcp/Tools/GetFarmOverview.php`, `app/Http/Middleware/MarkMcpRequest.php`, `resources/views/mcp/authorize.blade.php` (publish แล้วแปลข้อความเป็นไทย)
- Modify: `composer.json` (`laravel/mcp`, `laravel/passport` เข้า `require`), `app/Models/User.php`, `config/auth.php` (guard `api` driver `passport`), `app/Providers/AppServiceProvider.php`, `bootstrap/app.php` (ถ้า `routes/ai.php` ไม่ถูกโหลดเอง)
- Test: `tests/Feature/Mcp/FarmServerAuthTest.php`, `tests/Feature/Mcp/GetFarmOverviewTest.php`

**Interfaces:**
- Produces:
  - Route: `Mcp::web('/mcp/farm', FarmServer::class)->middleware(['auth:api', MarkMcpRequest::class])` และ `Mcp::oauthRoutes()` ใน `throttle:10,1`
  - `MarkMcpRequest` ทำ `Context::add(Provenance::VIA_KEY, 'mcp')`
  - `FarmServer`
    - `#[Name('Sia Diao Farm')]`
    - `#[Instructions]` เป็นภาษาอังกฤษ ให้มีเนื้อหาตาม spec MCP Goal 9: ลำดับแหล่งความรู้ (กฎความปลอดภัยจากระบบ → ผลจริงของสวน → โน้ตความรู้ → เว็บ), ต้องทวนสรุปและได้รับการยืนยันในแชตก่อนเรียก tool ที่เขียน แก้ หรือลบทุกครั้ง, ตอบผู้ใช้เป็นภาษาไทย, อ้างต้นด้วยรหัส, ห้ามแต่งอัตราการใช้ยาหรือระยะปลอดสารขึ้นเอง และให้เรียก `get_farm_overview` ก่อนเสมอ
  - `GetFarmOverview` (`#[IsReadOnly]`, ไม่มี argument) คืน JSON:
    - `orchards[]` (id, name, current season, active tree count)
    - `due_issues[]`, `open_issues[]` (`IssueSummary` + `orchard_id`)
    - `findings[]` (id, name, kind)
    - `today`
  - `User` implements `OAuthenticatable` และใช้ `HasApiTokens`
  - convention ของทุก tool: คืนค่าเป็น `Response::json(...)` ส่วน error ใช้ `$request->validate()` พร้อมข้อความภาษาอังกฤษที่บอกวิธีแก้

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('mcp endpoint rejects unauthenticated requests')`: POST `/mcp/farm` ได้ 401
  - `test('oauth discovery route is registered')`: GET `/.well-known/oauth-protected-resource` ได้ 200 (ตรวจ path จริงใน `Mcp::oauthRoutes` ก่อน)
  - `test('overview lists orchards due issues and findings')`: `FarmServer::actingAs($user)->tool(GetFarmOverview::class)` → `assertOk()->assertSee(['สวนหน้าบ้าน', 'ไรแดง'])`
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** `composer require laravel/mcp laravel/passport` แล้วรัน `php artisan install:api --passport` (ตรวจ `--help` ก่อน และอย่าให้ไปทับ `routes/web.php`), `php artisan vendor:publish --tag=mcp-views` และตั้ง `Passport::authorizationView` จากนั้นเขียน server, tool และ middleware
- [x] **Step 4:** รัน `tests/Feature/Mcp` และทั้ง suite (`php artisan test --compact`) → ต้อง PASS
- [x] **Step 5:** Commit `feat: farm MCP server with OAuth`

### Task 8: เครื่องมืออ่านข้อมูล

**Files:**
- Create: `app/Mcp/Tools/GetTree.php`, `ListRecords.php`, `GetTreatmentOutcomes.php`, `app/Mcp/Support/TreeLookup.php`
- Modify: `FarmServer.php`
- Test: `tests/Feature/Mcp/ReadToolsTest.php`

**Interfaces:**
- Produces:
  - `TreeLookup::resolve(?int $orchardId, array $codes): Collection<int, Tree>`
    - ถ้า `$orchardId` เป็น null และมีสวนเดียว ให้ใช้สวนนั้น ถ้ามีหลายสวน throw `ValidationException` key `orchard_id` ข้อความ `"Multiple orchards exist; pass orchard_id from get_farm_overview."`
    - ถ้าหารหัสไม่เจอ throw key `tree_codes` พร้อมรายการรหัสที่ไม่เจอ
    - ต้นที่ถูก remove ยังคืนมา (ให้ action ตัดสินเอง)
  - `GetTree` (RO; `orchard_id?`, `code`) คืน: tree, `open_issues`, `latest_stage`, `timeline` (ใช้ `TreeTimeline::for` จำกัด 30 รายการ), `season_yields`
  - `ListRecords` (RO)
    - argument: `type` ∈ `care|harvest|sale|expense|observation|issue`, `orchard_id?`, `tree_code?`, `from?`, `to?`, `include_deleted` (default false), `limit` (default 50, max 200)
    - คืน record ที่มี `id` (ใช้กับ `delete_record`/`restore_record`/`save_*`) เรียงใหม่ไปเก่า
  - `GetTreatmentOutcomes` (RO; `finding?`)
    - จัดกลุ่มตาม (ชื่อ finding, `care_records.product`) จาก issue ที่ผูกกับการดูแล
    - คืน `issues_treated`, `resolved`, `avg_days_to_resolve` (คิดเฉพาะเรื่องที่ปิดแล้ว: resolved_on − opened_on)

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('ambiguous tree code without orchard id is rejected')`: มี 2 สวน → `assertHasErrors(['Multiple orchards exist; pass orchard_id from get_farm_overview.'])`
  - `test('single orchard resolves code without orchard id')`
  - `test('get tree returns open issues and timeline')`
  - `test('list records filters by type date and excludes deleted by default')`
  - `test('treatment outcomes count resolved issues per product')`: ไรแดง 3 เรื่องที่ผูกกับ "อะบาเมกติน" ปิดแล้ว 2 เรื่อง (ใช้เวลา 7 และ 11 วัน) → `issues_treated` 3, `resolved` 2, `avg_days_to_resolve` 9
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** เขียน tool และลงทะเบียนใน `FarmServer::$tools`
- [x] **Step 4:** รัน `tests/Feature/Mcp` → ต้อง PASS
- [x] **Step 5:** Commit `feat: MCP read tools`

### Task 9: เครื่องมือเขียนข้อมูล

**Files:**
- Create: `app/Mcp/Tools/SaveCareRecord.php`, `RecordObservation.php`, `RecordFollowUp.php`, `SaveHarvest.php`, `SaveSale.php`, `SaveExpense.php`
- Modify: `FarmServer.php`
- Test: `tests/Feature/Mcp/WriteToolsTest.php`

**Interfaces:**
- Consumes: action จาก Task 3–4, `TreeLookup`, rules ของ `Store*Request` เดิม (`(new StoreSaleRequest)->rules()` ใช้ได้เฉพาะกรณีที่ rules ไม่อ้าง route)
- Produces (ทุกตัวคืน record ที่บันทึกแล้วพร้อม `id`; tool ที่รับ `id` จะ update แทน create):
  - `save_care_record`: `id?`, `orchard_id?`, `type`, `performed_on`, `product?`, `quantity?`, `notes?`, `tree_codes[]`, `issue_ids?` (ถ้าไม่ส่งมา ใช้ `SaveCareRecord::defaultIssueIds`)
  - `record_observation`: `id?` (แก้ได้แค่ observed_on/severity/notes), `orchard_id?`, `tree_codes[]`, `finding`, `kind`, `observed_on`, `severity?`, `notes?`, `follow_up_on?` → สร้าง 1 observation ต่อต้น ผ่าน `RecordObservation`
  - `record_follow_up`: `issue_id`, `observed_on`, `outcome`, `notes?`, `next_follow_up_on?`
  - `save_harvest`: `id?`, `orchard_id?`, `tree_code`, `harvested_on`, `weight_kg`, `fruit_count?`, `notes?` (ต้นที่ถูก remove → error)
  - `save_sale`: `id?`, `orchard_id`, `sold_on`, `buyer_name?`, `weight_kg`, `amount`, `notes?`
  - `save_expense`: `id?`, `season_id?`, `category`, `spent_on`, `amount`, `notes?`
  - description ของทุก tool ขึ้นต้นด้วย `"Only call after the user confirmed a summary in chat."`

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('save care record via mcp marks provenance mcp')`: `created_via` = `'mcp'`, `created_by` = user
  - `test('pesticide via mcp links open issues by default')`
  - `test('record observation for multiple trees appends to existing open issue')`: A1 มีไรแดงเปิดอยู่แล้ว + บันทึก A1, A2 → issue 2 เรื่อง (ไม่ใช่ 3)
  - `test('record follow up resolves issue')`
  - `test('save harvest on removed tree returns error')`
  - `test('update via id changes existing record and sets updated_via mcp')`
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** เขียน tool ทั้ง 6 ตัวให้เรียก action หรือ model ตาม Interfaces แล้วลงทะเบียน
- [x] **Step 4:** รัน `tests/Feature/Mcp` → ต้อง PASS
- [x] **Step 5:** Commit `feat: MCP write tools`

### Task 10: โน้ตความรู้ + ลบ/กู้คืน

**Files:**
- Create: `app/Models/FarmNote.php` + migration + factory; `app/Mcp/Tools/SaveNote.php`, `ListNotes.php`, `DeleteRecord.php`, `RestoreRecord.php`
- Modify: `FarmServer.php`
- Test: `tests/Feature/Mcp/NotesAndDeletionTest.php`

**Interfaces:**
- Produces:
  - `farm_notes`: `id, body text, finding_id nullable (nullOnDelete), tree_id nullable (nullOnDelete), timestamps, softDeletes, Provenance::columns`
  - `save_note`: `id?`, `body`, `finding?` (ชื่อ → `Finding::resolveByName` โดยใช้ kind ที่ AI ส่งมาเมื่อเป็นชื่อใหม่ ถ้าไม่ส่ง default `problem`), `orchard_id?` + `tree_code?`
  - `list_notes` (RO): `finding?`, `tree_code?` (เมื่อกรอง ให้รวมโน้ตทั่วไปที่ไม่ผูกอะไรมาด้วย)
  - `delete_record` (`#[IsDestructive]`): `type` ∈ `care|harvest|sale|expense|observation|issue|note`, `id` → soft delete คืน `"Deleted. It can be restored with restore_record."`
  - `restore_record`: `type`, `id` → restore
  - การ map ประเภทเป็น model ใช้ `match` ในที่เดียว (`app/Mcp/Support/RecordTypes.php`) และใช้ร่วมกับ `ListRecords`

- [x] **Step 1: เขียน test ที่ต้อง fail**
  - `test('save and list notes filtered by finding includes general notes')`
  - `test('delete record soft deletes and restore brings it back')`: ทำกับ care record และ note
  - `test('deleting an issue hides it from overview due list')`
  - `test('delete tool is annotated destructive')`
- [x] **Step 2:** รัน test → ต้อง FAIL
- [x] **Step 3:** เขียน model และ tool (ย้าย type map ของ `ListRecords` มาใช้ `RecordTypes`)
- [x] **Step 4:** รันทั้ง suite (`php artisan test --compact`) + `vendor/bin/pint --dirty --format agent` → ต้อง PASS
- [x] **Step 5:** Commit `feat: farm notes and MCP delete/restore`

---

## Self-review notes

- **ครอบคลุม spec:**
  - observations ข้อ 1–9 → Task 2–6
  - MCP Goal 1–7, 9, 10 → Task 1, 7–10
  - Goal 8 (กฎความปลอดภัย) เป็นระยะ C ไม่อยู่ในแผนนี้
  - ส่วนที่ตัดออกตามแนวทาง lean อยู่ในหัวข้อ "Lean cuts"
- **Deploy (Docker/VPS + HTTPS + Passport keys ใน production)** เป็นงานแยก แต่ต้อง deploy เสร็จก่อนถึงจะทดสอบกับ Claude จริงได้
