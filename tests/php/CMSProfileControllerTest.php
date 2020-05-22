<?php

namespace SilverStripe\Admin\Tests;

use SilverStripe\Admin\CMSProfileController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;

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
    public function testMemberEditsOwnProfile($assert, $required_permission_codes, $identifier)
    {
        CMSProfileController::config()->update('required_permission_codes', $required_permission_codes);

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
        $adminPermissionCodes = $this->objFromFixture(Group::class, 'admins')->columnUnique('Code');
        $cmsusersPermissionCodes = $this->objFromFixture(Group::class, 'cmsusers')->columnUnique('Code');

        return [
            ['CMS_ACCESS', 'admin', 'assertEquals'],
            ['CMS_ACCESS', 'user3', 'assertEquals'],
            ['CMS_ACCESS', 'nocms', 'assertNotEquals'],

            [true, 'admin', 'assertEquals'],
            [true, 'user3', 'assertEquals'],
            [true, 'nocms', 'assertEquals'],

            ['CUSTOM', 'admin', 'assertNotEquals'],
            ['CUSTOM', 'user3', 'assertNotEquals'],
            ['CUSTOM', 'nocms', 'assertEquals'],

            [$adminPermissionCodes, 'admin', 'assertEquals'],
            [$adminPermissionCodes, 'user3', 'assertNotEquals'],
            [$adminPermissionCodes, 'nocms', 'assertNotEquals'],

            [$cmsusersPermissionCodes, 'admin', 'assertEquals'],
            [$cmsusersPermissionCodes, 'user3', 'assertEquals'],
            [$cmsusersPermissionCodes, 'nocms', 'assertNotEquals']
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
