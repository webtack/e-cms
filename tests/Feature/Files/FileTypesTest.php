<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Webtack\Ecms\Files\FileTypes;
use Webtack\Ecms\Tests\TestCase;

class FileTypesTest extends TestCase
{
    public function test_a_type_of_a_profile_gets_its_extension(): void
    {
        $this->assertSame('docx', FileTypes::label('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('pdf', FileTypes::label('application/pdf'));
    }

    public function test_jpeg_is_labelled_like_the_stored_extension(): void
    {
        $this->assertSame('jpg', FileTypes::label('image/jpeg'));
    }

    public function test_a_type_no_profile_lists_falls_back_to_the_part_after_the_slash(): void
    {
        $this->assertSame('plain', FileTypes::label('text/plain'));
        $this->assertSame('weird', FileTypes::label('weird'));
    }

    public function test_labels_and_options_follow_the_profiles(): void
    {
        $this->assertSame('xlsx', FileTypes::labels()['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        $this->assertSame(['jpg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xlsx'], FileTypes::options());
    }

    public function test_an_extension_shared_by_several_mimes_finds_all_of_them(): void
    {
        config(['files.profiles.image.mimes' => ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg']]);

        $this->assertSame(['image/jpeg', 'image/pjpeg'], FileTypes::mimesFor('jpg'));
        $this->assertSame('jpg', FileTypes::label('image/pjpeg'));
        $this->assertSame(1, count(array_keys(FileTypes::options(), 'jpg', true)));
    }

    public function test_an_unknown_label_has_no_mimes(): void
    {
        $this->assertSame([], FileTypes::mimesFor('nope'));
    }
}
