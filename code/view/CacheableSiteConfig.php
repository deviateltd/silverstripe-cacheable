<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 7:23 PM
 */

class CacheableSiteConfig extends CacheableData{
    private static $cacheable_fields = array(
        "CanViewType",
    );

    private static $cacheable_functions = array(
        "ViewerGroups",
    );

    public function canView($member = null) {
        if(!$member) $member = Member::currentUserID();
        if($member && is_numeric($member)) $member = DataObject::get_by_id('Member', $member);

        if ($member && Permission::checkMember($member, "ADMIN")) return true;

        if (!$this->CanViewType || $this->CanViewType == 'Anyone') return true;

        // check for any logged-in users
        if($this->CanViewType == 'LoggedInUsers' && $member) return true;

        // check for specific groups
        if($this->CanViewType == 'OnlyTheseUsers' && $member && $member->inGroups($this->ViewerGroups)) return true;

        return false;
    }
}