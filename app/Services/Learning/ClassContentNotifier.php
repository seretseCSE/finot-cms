<?php

namespace App\Services\Learning;

use App\Models\ClassAnnouncement;
use App\Models\ClassMaterial;
use App\Models\HomeworkAssignment;
use App\Models\MemberParentGuardian;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Contracts\PushNotificationServiceInterface;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Collection;

class ClassContentNotifier
{
    public function __construct(
        protected Notifier $notifier,
        protected PushNotificationServiceInterface $push,
    ) {
    }

    public function announcePublished(ClassAnnouncement $announcement): void
    {
        $users = $this->recipientsForClass((int) $announcement->class_id);
        if ($users->isEmpty()) {
            return;
        }

        $title = $announcement->title;
        $body = \Illuminate\Support\Str::limit(strip_tags($announcement->body), 120);
        $link = '/admin/class-announcements-student';
        $data = [
            'title' => $title,
            'body' => $body,
            'class_id' => $announcement->class_id,
            'announcement_id' => $announcement->id,
        ];

        $this->notifier->toUsers($users, 'class.announcement', $data, $link, 'class-announcement-'.$announcement->id);
        $this->push->sendToUsers($users->pluck('id')->all(), $title, $body, [
            'url' => $link,
            'type' => 'class.announcement',
            'id' => $announcement->id,
        ]);
    }

    public function homeworkPublished(HomeworkAssignment $homework): void
    {
        $users = $this->recipientsForClass((int) $homework->class_id);
        if ($users->isEmpty()) {
            return;
        }

        $title = $homework->title;
        $body = $homework->due_at
            ? __('Due :date', ['date' => $homework->due_at->toDateString()])
            : __('New homework for your class');
        $link = '/admin/my-homework';
        $data = [
            'title' => $title,
            'body' => $body,
            'class_id' => $homework->class_id,
            'homework_id' => $homework->id,
        ];

        $this->notifier->toUsers($users, 'class.homework', $data, $link, 'homework-'.$homework->id);
        $this->push->sendToUsers($users->pluck('id')->all(), $title, (string) $body, [
            'url' => $link,
            'type' => 'class.homework',
            'id' => $homework->id,
        ]);
    }

    public function materialPublished(ClassMaterial $material): void
    {
        $users = $this->recipientsForClass((int) $material->class_id);
        if ($users->isEmpty()) {
            return;
        }

        $title = $material->title;
        $body = __('New class material shared');
        $link = '/admin/class-materials-student';
        $data = [
            'title' => $title,
            'body' => $body,
            'class_id' => $material->class_id,
            'material_id' => $material->id,
        ];

        $this->notifier->toUsers($users, 'class.material', $data, $link, 'material-'.$material->id);
        $this->push->sendToUsers($users->pluck('id')->all(), $title, (string) $body, [
            'url' => $link,
            'type' => 'class.material',
            'id' => $material->id,
        ]);
    }

    /**
     * Active enrollments for the class → student users + parent users of those members.
     *
     * @return Collection<int, User>
     */
    public function recipientsForClass(int $classId): Collection
    {
        $memberIds = StudentEnrollment::query()
            ->active()
            ->where('class_id', $classId)
            ->pluck('member_id')
            ->unique()
            ->filter()
            ->values();

        if ($memberIds->isEmpty()) {
            return collect();
        }

        $studentUsers = User::query()
            ->whereIn('member_id', $memberIds)
            ->get();

        $parentIds = MemberParentGuardian::query()
            ->whereIn('member_id', $memberIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->filter();

        $parentUsers = $parentIds->isEmpty()
            ? collect()
            : User::query()->whereIn('parent_id', $parentIds)->get();

        return $studentUsers->concat($parentUsers)->unique('id')->values();
    }
}
