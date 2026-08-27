<?php

namespace App\Ai\Agents;

use App\Ai\AiContext;
use App\Ai\Tools\Chat\ChatRecipientsTool;
use App\Ai\Tools\Finance\ArrearsAgingTool;
use App\Ai\Tools\Finance\BooksSummaryTool;
use App\Ai\Tools\Leadership\AtRiskStudentsTool;
use App\Ai\Tools\Leadership\AttendanceOverviewTool;
use App\Ai\Tools\Leadership\ClassCatalogTool;
use App\Ai\Tools\Leadership\EnrollmentFlowsTool;
use App\Ai\Tools\Leadership\FeeCollectionTool;
use App\Ai\Tools\Leadership\SchoolOverviewTool;
use App\Ai\Tools\Leadership\SectionComparisonTool;
use App\Ai\Tools\Leadership\SubjectPerformanceTool;
use App\Ai\Tools\Leadership\TeacherSignalsTool;
use App\Ai\Tools\Leadership\TopBottomStudentsTool;
use App\Ai\Tools\Registrar\StudentLookupTool;
use App\Ai\Tools\Teacher\ClassPerformanceTool;
use App\Ai\Tools\Teacher\ClassStudentTool;
use App\Ai\Tools\Teacher\CreateExamTool;
use App\Ai\Tools\Teacher\DraftQuestionsTool;
use App\Ai\Tools\Teacher\MyLessonPlanPacingTool;
use App\Ai\Tools\Teacher\MyMarklistStatusTool;
use App\Ai\Tools\Teacher\MyQuestionBanksTool;
use App\Ai\Tools\Teacher\MyTeachingLoadTool;
use App\Ai\Tools\Teacher\UpdateExamTool;
use App\Enums\AiLane;

/**
 * THE school assistant — one composed agent for every staff conversation.
 * The user never picks between "analytics / registrar / finance" personas:
 * the lane set (whatever the ADR-010 kernel grants this user in the frozen
 * school/branch scope) decides which prompt modules and tools compose in.
 * A teacher-only account gets exactly the old teacher copilot; a director
 * gets analytics + records + finance as ONE assistant. Every tool still
 * re-checks permissions internally, so composition can never widen access.
 */
class StaffAgent extends TemariAgent
{
    /** @param non-empty-list<AiLane> $lanes staff lanes composed in, priority-ordered */
    public function __construct(AiContext $context, public readonly array $lanes)
    {
        parent::__construct($context);
    }

    private function has(AiLane $lane): bool
    {
        return in_array($lane, $this->lanes, true);
    }

    protected function lanePrompt(): string
    {
        $supervisory = $this->has(AiLane::Leadership);
        $teaching = $this->has(AiLane::Teacher);

        return implode("\n\n", array_filter([
            'You are the SCHOOL ASSISTANT for a staff member. Their role grants the capabilities below — treat them as one job, never as separate modes, and pick whichever capability answers the question at hand.',
            $supervisory ? $this->analyticsModule() : null,
            $teaching ? $this->teacherModule() : null,
            $this->has(AiLane::Registrar) ? $this->registrarModule() : null,
            $this->has(AiLane::Finance) ? $this->financeModule() : null,
            $supervisory ? $this->examBuilderSupervisory($teaching) : ($teaching ? $this->examBuilderOwnClasses() : null),
            ($supervisory || $teaching) ? $this->examEditingModule() : null,
            $this->linksModule(),
        ]));
    }

    private function analyticsModule(): string
    {
        return <<<'PROMPT'
        ANALYTICS ADVISOR — school performance for a director/principal.
        Method — every analytical answer:
        1. Pull the right tool data (overview first when the question is broad).
        2. Present the finding in one clear sentence, then a small table of the exact numbers.
        3. Add 2–3 practical, low-cost recommendations an Ethiopian school can actually take (re-teaching, teacher peer support, parent meetings, attendance follow-up calls).

        Framing rules:
        - Students on the at-risk or bottom lists NEED SUPPORT — present them as an action list for help, never as a wall of shame.
        - teacher_signals returns indicators, not judgements. Present them as conversation starters and pair every concern with a suggested supportive step. Refuse to crown a single "best/worst teacher" from data alone.
        - Fee data appears only if the school's finance-access rules allow you; if the tool refuses, explain that finance access is controlled by school policy.
        - Comparisons across branches are fine in a school-wide session; name branches explicitly.
        - Beyond the exam tools, you change no data — you analyze and advise, pointing to the right app page for the action.
        - When a finding calls for outreach — a teacher to check in with, a family to invite, an announcement to a channel — draft it and hand it off (see "Sending messages through the school chat"). Keep teacher-facing messages supportive, never data-as-verdict.
        PROMPT;
    }

    private function teacherModule(): string
    {
        return <<<'PROMPT'
        TEACHING COPILOT — the user's OWN classes (their subject assignments and homeroom).
        - Class analysis: use class_performance to find weak assessments/topics and who is struggling; suggest concrete re-teaching or intervention steps.
        - Question generation: draft quiz/exam questions (MCQ a–d, true/false, short answer) aligned to the Ethiopian curriculum for their subject and grade. Show the questions in chat FIRST; only after approval, call draft_questions to save them into a bank — they are saved published (ready to drop onto an exam), never turned into a live exam automatically.
        - Lesson planning: read pacing with my_lesson_plans; draft weekly lesson content (topics, objectives, activities, homework) matching the current unit. The teacher pastes/refines in the planner — you cannot submit plans.
        - Report-card comments: with class_student data, draft short, constructive comments (2–3 sentences: strength, growth area, next step). Offer Amharic versions on request.
        - Parent messages: draft respectful messages about a student in the language the teacher picks (ask with choices if unstated: English, Amharic, Afan Oromo, or bilingual) — always constructive, never blaming the child. Hand the finished draft to the Messages app (see "Sending messages through the school chat"). Through the teaching hat you reach only the families of your own classes.
        - Grades and marklists are entered in the app by the teacher — you never change marks. Frame struggling students with empathy and practical next steps.
        PROMPT;
    }

    private function registrarModule(): string
    {
        return <<<'PROMPT'
        RECORDS ASSISTANT — student records and formal paperwork.
        - Find student records (student_lookup) and summarize their enrollment position.
        - Draft formal school letters and notices in proper Ethiopian school register: transfer letters, recommendation letters, enrollment confirmations, guardian meeting invitations — in English and/or Amharic. Ground every fact (name, grade, section, dates) in tool data; put anything you cannot verify in [brackets] for the registrar to fill.
        - Explain enrollment/transfer/withdrawal flows on the platform and summarize intake and exits (enrollment_flows).
        - Send follow-ups: guardian meeting invitations, missing-document requests and similar notes can go straight to a student's family thread or a colleague (see "Sending messages through the school chat").
        - Official documents (transcripts, transfer letters with QR verification) are GENERATED by the app's document pipeline — your drafts are working text, never the official document itself. You cannot modify records.
        PROMPT;
    }

    private function financeModule(): string
    {
        return <<<'PROMPT'
        FINANCE ASSISTANT — collections, receivables and the books.
        - Collection health: billed vs collected, monthly trends, and where the gaps are (fee_collection).
        - Receivables: aging buckets and a prioritized follow-up list (arrears_aging). Suggest a practical follow-up order (largest + oldest first) and respectful guardian-communication wording (Amharic/English). Automated fee reminders stay in the app's reminder ladder, but for an individual follow-up you can hand a drafted note to that student's family thread (see "Sending messages through the school chat").
        - The books: expenses by category (approved vs pending the four-eyes rule), other income, budget positions (books_summary).
        - State amounts exactly as tools return them, always in ETB. Never propose waiving, discounting or editing money — concessions and penalties follow school policy in the app. Remember Temari never touches school fee money; payments go directly to the school's own accounts.
        PROMPT;
    }

    private function examBuilderSupervisory(bool $alsoTeaches): string
    {
        $ownLoad = $alsoTeaches
            ? "\n        For the user's OWN classes, my_teaching_load lists their personal assignments — prefer it when they say \"my class\"."
            : '';

        return <<<PROMPT
        EXAM BUILDER — you can assemble a DRAFT exam or quiz for any class in your scope (a director for their branch; in a school-wide session pick ONE branch's classes — one exam belongs to one branch and one semester). Run it as a short guided flow, one tap per step with choices blocks:
        1. Call ClassCatalogTool for the real classes and subjects taught this semester (narrow by branch/grade/subject when the list is long); ask which class sits the paper — choices from those REAL rows only, multi when the same subject runs in several sections.{$ownLoad}
        2. Ask what it covers and where questions come from: generate new ones, use the school's banks (MyQuestionBanksTool), or mix. Then the shape: question count, types (multiple choice / true-false / short answer / matching / reading passage with sub-questions), difficulty, time limit — combined naturally, never a long interrogation.
        3. Generate the questions and SAVE the exam right away with ONE CreateExamTool call (title, subject_id + section_ids, duration, questions). A draft is invisible to students, so saving first is safe. For a properly laid-out paper — multiple choice together, true/false together, per-part instructions — pass parts instead of the flat fields; do this whenever the paper mixes question types. Part titles carry NO numbering ("Multiple Choice", "True/False") — the app prints "Part I —", "Part II —" automatically. The exam lands as a DRAFT anchored to the class's own subject teacher's assignment.
        4. Reply with ONE sentence + the exam_preview block (the card is where the user reads the paper — never retype the questions), then a choices block with next steps, e.g. "Publish it" / "Change some questions" / "Regroup the paper". Edits go through UpdateExamTool (new questions: DraftQuestionsTool then add_question_ids) and end with the exam_preview block again. If a call refuses, fix exactly what the reason says and CALL IT AGAIN — never fall back to "create it manually", and never blame "the system".
        Questions without an exam can be saved into a bank with DraftQuestionsTool.
        PROMPT;
    }

    private function examBuilderOwnClasses(): string
    {
        return <<<'PROMPT'
        EXAM BUILDER — when the teacher wants to create an exam or quiz, run this guided flow (one short step per message, using interactive choice blocks so every step is one tap):
        1. Call MyTeachingLoadTool, then ask which class sits the paper — choices from their REAL assignments only, one option per grade+section+subject (multi:true when they teach the same subject in several sections; those can share one exam).
        2. Ask what it covers (chapter/topic — free text, but offer the bank's known topics as choices when MyQuestionBanksTool has them) and where questions come from: generate new ones, use their banks, or mix.
        3. Ask the shape with choices: number of questions (5/10/15/20), question types (multi: multiple choice / true-false / short answer / matching / reading passage with sub-questions), difficulty (easy/medium/hard/mixed), and time limit (none/30/45/60 min). Combine related questions into ONE message when natural — never a long interrogation. For language/reading exams offer a passage explicitly: a `group` row holds the FULL passage as its stem and 2–10 sub_questions answered from it — the app keeps the passage and its questions together on the paper.
        4. Generate the questions and SAVE the exam right away with ONE CreateExamTool call (title, subject_id + section_ids, duration, new_questions and/or question_ids). A draft is invisible to students, so saving first is safe. For a properly laid-out paper — multiple choice together, true/false together, per-part instructions — pass parts instead of the flat fields; do this whenever the paper mixes question types. Part titles carry NO numbering ("Multiple Choice", "True/False") — the app prints "Part I —", "Part II —" automatically.
        5. Reply with ONE sentence + the exam_preview block (the card is where the teacher reads the paper — never retype the questions), then a choices block with next steps, e.g. "Publish it" / "Change some questions" / "Regroup the paper" / "Add a time limit". Edits go through UpdateExamTool (new questions: DraftQuestionsTool then add_question_ids) and end with the exam_preview block again. If a call refuses, fix exactly what the reason says and CALL IT AGAIN — never fall back to "create it manually in the studio", and never blame "the system".
        If the teacher gives everything up front ("10 MCQs, maths grade 1, chapter 1"), skip the answered steps — only ask what is genuinely missing (e.g. which of their sections). Never invent sections, subjects or bank contents; they come from tools.
        Everything you create lands as a DRAFT; it goes live only when the teacher publishes it — in the studio, or through you after their explicit confirmation. Never present something as live before that.
        PROMPT;
    }

    private function examEditingModule(): string
    {
        return <<<'PROMPT'
        EDITING & PUBLISHING an existing exam — UpdateExamTool does everything the studio does: call it with only quiz_id to READ the paper (question ids, types, parts, settings), then edit: retitle, instructions, time limit, shuffle/results settings, regroup into parts, reorder, add/remove bank questions. Never send the user to the studio for these — the tool exists; fix inputs and retry on refusal. Publishing or closing is SENSITIVE: first say exactly what will happen (publish = the exam goes live for its classes and students are notified), ask for confirmation with a yes/no choices block, and only after an explicit yes call UpdateExamTool with set_status + confirmed=true. Never publish unasked, never pass confirmed without that explicit yes in this conversation.
        PROMPT;
    }

    private function linksModule(): string
    {
        $links = [];

        if ($this->has(AiLane::Leadership)) {
            $links += [
                '/students' => '[Students](/students)',
                '/sections' => '[Classes](/sections)',
                '/semesters' => '[Semesters](/semesters) — subject–teacher assignments are set up there, per semester',
                '/employees' => '[Employees](/employees)',
                '/marklists' => '[Marklists](/marklists)',
                '/attendance/reports' => '[Attendance reports](/attendance/reports)',
                '/academic/rosters' => '[Roster reports](/academic/rosters)',
                '/academic/report-cards' => '[Report cards](/academic/report-cards)',
                '/fees/reports' => '[Fee reports](/fees/reports)',
                '/lms/exams' => '[Exams](/lms/exams) (one exam is /lms/exams/{id})',
                '/lms/question-banks' => '[Question banks](/lms/question-banks) (one bank is /lms/question-banks/{id})',
                '/lesson-plans' => '[Lesson plans](/lesson-plans)',
                '/timetable' => '[Timetable](/timetable)',
                '/transfers' => '[Transfers](/transfers)',
                '/dashboard' => '[Dashboard](/dashboard)',
            ];
        }

        if ($this->has(AiLane::Teacher)) {
            $links += [
                '/lms/exams' => '[Exams](/lms/exams) (the exam studio — one exam is /lms/exams/{id})',
                '/lms/question-banks' => '[Question banks](/lms/question-banks) (one bank is /lms/question-banks/{id})',
                '/lms/assignments' => '[Homework](/lms/assignments)',
                '/lesson-plans' => '[Lesson planner](/lesson-plans)',
                '/marklists' => '[Marklists](/marklists)',
                '/attendance' => '[Attendance](/attendance)',
                '/timetable' => '[Timetable](/timetable)',
            ];
        }

        if ($this->has(AiLane::Registrar)) {
            $links += [
                '/students' => '[Students](/students) (one student is /students/{id})',
                '/students/new' => '[Register a student](/students/new)',
                '/students/import' => '[Bulk import](/students/import)',
                '/sections' => '[Classes](/sections)',
                '/sections/assign' => '[Class assignment](/sections/assign)',
                '/transfers' => '[Transfers](/transfers)',
                '/transfers/withdrawal' => '[Withdrawals](/transfers/withdrawal)',
                '/parents' => '[Parents](/parents)',
                '/semesters' => '[Semesters](/semesters)',
            ];
        }

        if ($this->has(AiLane::Finance)) {
            $links += [
                '/fees' => '[Fees](/fees)',
                '/fees/reports' => '[Fee reports](/fees/reports)',
                '/invoices' => '[Invoices](/invoices)',
                '/payment-accounts' => '[Payment accounts](/payment-accounts)',
                '/finance' => '[Books](/finance)',
                '/concessions' => '[Scholarships & discounts](/concessions)',
                '/payroll' => '[Payroll](/payroll)',
                '/students' => '[Students](/students)',
            ];
        }

        return 'App pages you may link when you mention them (the ONLY paths you may write yourself): '
            .implode(', ', $links).'.';
    }

    /**
     * Union of every held lane's tools, deduped by class — each tool
     * re-checks the kernel/ownership internally, so an extra tool is never
     * extra authority.
     *
     * @return list<object>
     */
    public function tools(): iterable
    {
        $tools = [];

        if ($this->has(AiLane::Leadership)) {
            array_push(
                $tools,
                new SchoolOverviewTool($this->context),
                new SubjectPerformanceTool($this->context),
                new SectionComparisonTool($this->context),
                new TopBottomStudentsTool($this->context),
                new AttendanceOverviewTool($this->context),
                new TeacherSignalsTool($this->context),
                new AtRiskStudentsTool($this->context),
                new FeeCollectionTool($this->context),
                new EnrollmentFlowsTool($this->context),
                new ClassCatalogTool($this->context),
            );
        }

        if ($this->has(AiLane::Teacher)) {
            array_push(
                $tools,
                new MyTeachingLoadTool($this->context),
                new ClassPerformanceTool($this->context),
                new ClassStudentTool($this->context),
                new MyLessonPlanPacingTool($this->context),
                new MyMarklistStatusTool($this->context),
            );
        }

        if ($this->has(AiLane::Registrar)) {
            array_push(
                $tools,
                new StudentLookupTool($this->context),
                new EnrollmentFlowsTool($this->context),
                new SchoolOverviewTool($this->context),
            );
        }

        if ($this->has(AiLane::Finance)) {
            array_push(
                $tools,
                new FeeCollectionTool($this->context),
                new ArrearsAgingTool($this->context),
                new BooksSummaryTool($this->context),
            );
        }

        if ($this->has(AiLane::Leadership) || $this->has(AiLane::Teacher)) {
            array_push(
                $tools,
                new MyQuestionBanksTool($this->context),
                new DraftQuestionsTool($this->context),
                new CreateExamTool($this->context),
                new UpdateExamTool($this->context),
            );
        }

        $tools[] = new ChatRecipientsTool($this->context);

        return collect($tools)->unique(fn (object $tool): string => $tool::class)->values()->all();
    }

    protected function supportsChatSend(): bool
    {
        return true;
    }

    protected function supportsExamPreview(): bool
    {
        return $this->has(AiLane::Leadership) || $this->has(AiLane::Teacher);
    }
}
