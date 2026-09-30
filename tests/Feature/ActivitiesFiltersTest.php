<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivitiesPage;
use App\Models\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\ActivityTestCase;

class ActivitiesFiltersTest extends ActivityTestCase
{
    public function test_result_cap_limits_the_last_page_and_pages_beyond_it(): void
    {
        for ($id = 1; $id <= 40; $id++) {
            $this->event($id, ['created_at' => sprintf('2026-09-01 12:00:%02d', $id)]);
        }

        $page = new ActivitiesPage;
        $page->perPage = 15;
        $page->maxResults = 25;
        $page->paginators = ['page' => 2];

        $results = $page->getHistories();
        $this->assertSame(25, $results->total());
        $this->assertCount(10, $results->items());
        $this->assertSame(range(25, 16), $results->getCollection()->modelKeys());

        $page->paginators = ['page' => 3];
        $this->assertCount(0, $page->getHistories()->items());

        $page->paginators = ['page' => 1];
        $page->perPage = 'all';
        $this->assertCount(25, $page->getHistories()->items());
        $page->maxResults = 0;
        $this->assertCount(40, $page->getHistories()->items());
    }

    public function test_device_type_and_unknown_pet_filters_compose(): void
    {
        DB::table('pets')->insert(['id' => 1, 'name' => 'Ada']);
        $this->event(1, ['device_id' => 7, 'pet_id' => 1]);
        $this->event(2, ['device_id' => 7, 'pet_id' => null]);
        $this->event(3, ['device_id' => 7, 'pet_id' => 99]);
        $this->event(4, ['device_id' => 8, 'pet_id' => null]);
        $this->event(5, ['device_id' => 7, 'type' => 'ERROR']);

        $page = new ActivitiesPage;
        $page->deviceIds = [7];
        $page->petIds = [ActivitiesPage::UNKNOWN_PET];
        $page->types = ['DETECT'];
        $this->assertSame([2, 3], $this->matchingIds($page));
        $page->petIds[] = 1;
        $this->assertSame([1, 2, 3], $this->matchingIds($page));
    }

    public function test_media_filters_match_video_photo_and_info_events(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->event($id);
        }
        DB::table('media_files')->insert([
            ['event_id' => 'event-1', 'file_id' => 'clip.ts', 'file_type' => 'video/mp2t'],
            ['event_id' => 'event-2', 'file_id' => 'photo.jpg', 'file_type' => 'image/jpeg'],
        ]);

        $page = new ActivitiesPage;
        foreach (['video' => [1], 'photo' => [2], 'info' => [3]] as $type => $expected) {
            $page->mediaTypes = [$type];
            $this->assertSame($expected, $this->matchingIds($page));
        }
        $page->mediaTypes = ['video', 'photo', 'info'];
        $this->assertSame([1, 2, 3], $this->matchingIds($page));
    }

    public function test_date_and_overnight_time_filters_intersect(): void
    {
        $this->event(1, ['created_at' => '2026-09-01 23:15:00']);
        $this->event(2, ['created_at' => '2026-09-02 01:15:00']);
        $this->event(3, ['created_at' => '2026-09-02 12:00:00']);
        $this->event(4, ['created_at' => '2026-09-03 01:15:00']);

        $page = new ActivitiesPage;
        $page->dateFrom = '2026-09-01';
        $page->dateTo = '2026-09-02';
        $page->timeFrom = '22:00';
        $page->timeTo = '02:00';
        $this->assertSame([1, 2], $this->matchingIds($page));

        $page->dateFrom = (string) strtotime('2026-09-02 00:00:00 UTC');
        $this->assertSame([2], $this->matchingIds($page));
    }

    public function test_legacy_query_keys_and_unlimited_results_serialize_canonically(): void
    {
        $this->app->instance('request', Request::create('/activities', 'GET', [
            'deviceId' => '7',
            'petId' => 'unknown',
            'type' => 'DETECT',
            'mediaType' => 'photo',
            'dateFrom' => '2026-09-01',
            'dateTo' => '2026-09-02',
            'timeFrom' => '22:00',
            'timeTo' => '02:00',
            'maxResults' => 'all',
        ]));
        $page = new ActivitiesPage;
        $page->mountHasActivityFilters();

        $this->assertSame([
            'devices' => ['7'], 'pets' => ['unknown'], 'types' => ['DETECT'],
            'media' => ['photo'], 'date_from' => '2026-09-01', 'date_to' => '2026-09-02',
            'time_from' => '22:00', 'time_to' => '02:00', 'max_results' => 'all',
        ], $page->getActivityFilterQueryParams());
    }

    public function test_activities_renders_and_filters_without_later_features(): void
    {
        $this->event(1);
        Livewire::test(ActivitiesPage::class)
            ->assertSuccessful()
            ->assertSee('Activities Timeline')
            ->set('types', ['ERROR'])
            ->assertSee('No activities match the selected filters.');
    }

    private function matchingIds(ActivitiesPage $page): array
    {
        $query = History::query();
        $page->applyFilters($query);

        return $query->orderBy('id')->pluck('id')->all();
    }
}
