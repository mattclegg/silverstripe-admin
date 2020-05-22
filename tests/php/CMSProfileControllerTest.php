<?php

namespace SilverStripe\Admin\Tests;

use SilverStripe\Admin\CMSProfileController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;

class CMSProfileControllerTest extends FunctionalTest
{

    protected static $fixture_file = 'CMSProfileControllerTest.yml';

    public $autoFollowRedirection = false;

    public function testMemberCantEditAnother()
    {
        $member = $this->objFromFixture(Member::class, 'user1');
        $anotherMember = $this->objFromFixture(Member::class, 'user2');
        $this->session()->set('loggedInAs', $member->ID);

        $response = $this->post('admin/myprofile/EditForm', array(
            'action_save' => 1,
            'ID' => $anotherMember->ID,
            'FirstName' => 'JoeEdited',
            'Surname' => 'BloggsEdited',
            'Email' => $member->Email,
            'Locale' => $member->Locale,
            'Password[_Password]' => 'password',
            'Password[_ConfirmPassword]' => 'password',
        ));

        $anotherMember = $this->objFromFixture(Member::class, 'user2');

        $this->assertNotEquals($anotherMember->FirstName, 'JoeEdited', 'FirstName field stays the same');
    }

    /**
     * @dataProvider requiredPermissionCodesProvider
     */
    public function testMemberEditsOwnProfile($assert, $fixtureTest, $identifier)
    {
        CMSProfileController::config()->update('required_permission_codes', [
            $this->objFromFixture(CMSProfileController::class, $fixtureTest)
        ]);

        $member = $this->objFromFixture(Member::class, $identifier);
        $this->session()->set('loggedInAs', $member->ID);

        $response = $this->post('admin/myprofile/EditForm', array(
            'action_save' => 1,
            'ID' => $member->ID,
            'FirstName' => 'JoeEdited',
            'Surname' => 'BloggsEdited',
            'Email' => $member->Email,
            'Locale' => $member->Locale,
            'Password[_Password]' => 'password',
            'Password[_ConfirmPassword]' => 'password',
        ));

        $member = $this->objFromFixture(Member::class, $identifier);

        $this->$assert('JoeEdited', $member->FirstName, 'FirstName field was changed');
    }

    public function requiredPermissionCodesProvider()
    {
        return [
            ['default', 'admin', 'assertEquals'],
            ['default', 'user3', 'assertEquals'],
            ['default', 'nocms', 'assertNotEquals'],

            ['everybody', 'admin', 'assertEquals'],
            ['everybody', 'user3', 'assertEquals'],
            ['everybody', 'nocms', 'assertEquals'],

            ['custom', 'admin', 'assertNotEquals'],
            ['custom', 'user3', 'assertNotEquals'],
            ['custom', 'nocms', 'assertEquals'],

            ['admin', 'admin', 'assertEquals'],
            ['admin', 'user3', 'assertNotEquals'],
            ['admin', 'nocms', 'assertNotEquals'],

            ['cms', 'admin', 'assertEquals'],
            ['cms', 'user3', 'assertEquals'],
            ['cms', 'nocms', 'assertNotEquals']
        ];
    }

    public function testExtendedPermissionsStopEditingOwnProfile()
    {
        $existingExtensions = Member::config()->get('extensions', Config::EXCLUDE_EXTRA_SOURCES);
        Member::config()->update('extensions', [
            CMSProfileControllerTest\TestExtension::class
        ]);

        $member = $this->objFromFixture(Member::class, 'user1');
        $this->session()->set('loggedInAs', $member->ID);

        $response = $this->post('admin/myprofile/EditForm', array(
            'action_save' => 1,
            'ID' => $member->ID,
            'FirstName' => 'JoeEdited',
            'Surname' => 'BloggsEdited',
            'Email' => $member->Email,
            'Locale' => $member->Locale,
            'Password[_Password]' => 'password',
            'Password[_ConfirmPassword]' => 'password',
        ));

        $member = $this->objFromFixture(Member::class, 'user1');

        $this->assertNotEquals(
            $member->FirstName,
            'JoeEdited',
            'FirstName field was NOT changed because we modified canEdit'
        );

        Member::config()
            ->remove('extensions')
            ->update('extensions', $existingExtensions);
    }
}
