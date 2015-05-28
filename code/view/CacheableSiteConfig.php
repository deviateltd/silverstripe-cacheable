<?php
/**
 * @author Deviate Ltd 2014-2015 http://www.deviate.net.nz
 * @package silverstripe-cachable
 */
class CacheableSiteConfig extends CacheableData {
    
    /**
     *
     * @var array
     */
    private static $cacheable_fields = array(
        "CanViewType",
    );

    /**
     *
     * @var array
     */
    private static $cacheable_functions = array(
        "ViewerGroups",
    );

    /**
     * 
     * @param Member $member
     * @return boolean
     */
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
