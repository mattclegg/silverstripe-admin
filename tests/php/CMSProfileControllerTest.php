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
    public function testMemberEditsOwnProfile($assert, $identifier, $required_permission_codes='', $required_permission_code_group='')
    {
        if(!$required_permission_codes && $required_permission_code_group) {
            $required_permission_codes = $this->objFromFixture(
                Group::class,
                $required_permission_code_group
            )->columnUnique('Code');
        }

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

        if(is_array($required_permission_codes)){
            $required_permission_codes = implode("|", $required_permission_codes);
        }
        $this->$assert('JoeEdited', $member->FirstName, 'FirstName field was changed using '. $required_permission_codes);
    }

    public function requiredPermissionCodesProvider()
    {
        return [
            # Only CMS_ACCESS users
            ['assertEquals', 'admin', 'CMS_ACCESS'], #FAIL ##FAIL
            ['assertEquals', 'user3', 'CMS_ACCESS'],
            ['assertNotEquals', 'nocms', 'CMS_ACCESS'], ##FAIL

            # Everybody
            ['assertEquals', 'admin', true], #FAIL ##FAIL
            ['assertEquals', 'user3', true],
            ['assertEquals', 'nocms', true],

            # Only nocms users
            ['assertNotEquals', 'admin', 'CUSTOM'],
            ['assertNotEquals', 'user3', 'CUSTOM'], ##FAIL
            ['assertEquals', 'nocms', 'CUSTOM'],

            # Only admin group users
            ['assertEquals', 'admin', 'admins', true], #FAIL
            ['assertNotEquals', 'user3', 'admins', true], ##FAIL
            ['assertNotEquals', 'nocms', 'admins', true],

            # Only cmsusers group users
            ['assertNotEquals', 'admin', 'cmsusers', true],
            ['assertEquals', 'user3', 'cmsusers', true], #FAIL ##FAIL
            ['assertNotEquals', 'nocms', 'cmsusers', true] ##FAIL
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
