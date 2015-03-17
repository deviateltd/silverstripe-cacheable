<?php
/**
 * Created by PhpStorm.
 * User: normann.lou
 * Date: 15/03/2015
 * Time: 7:19 PM
 */

class CacheableSiteTree extends CacheableData{
    private static $cacheable_fields = array(
        "MenuTitle",
        "URLSegment",
        "ParentID",
        "ClassName",
        "Sort",
        "ShowInMenus",
        "CanViewType",
    );

    private static $cacheable_functions = array(
        "Link",
        "ViewerGroups",
        "getSourceQueryParams",
    );

    private $Children = array();

    private $Parent;

    public function getSiteConfig(){
        if($this->hasMethod('alternateSiteConfig')) {
            $altConfig = $this->alternateSiteConfig();
            if($altConfig) return $altConfig;
        }

        if($nav = Config::inst()->get('Cacheable', '_cached_navigation')){
            if($nav) {
                $site_map = $nav->get_site_map();
                $site_config = $nav->get_site_config();
                if(isset($site_map[$this->ID]) && $site_config->exists()) {
                    return $site_config;
                }
            }
        }

        return SiteConfig::current_site_config();
    }

    public function getCachedSourceQueryParam($key){
        if(isset($this->getSourceQueryParams[$key])) return $this->getSourceQueryParams[$key];
        else return null;
    }

    public function canView($member = null){
        if(!$member || !(is_a($member, 'Member')) || is_numeric($member)) {
            $member = Member::currentUserID();
        }

        // admin override
        if($member && Permission::checkMember($member, array("ADMIN", "SITETREE_VIEW_ALL"))) return true;
        // make sure we were loaded off an allowed stage

        // Were we definitely loaded directly off Live during our query?
        $fromLive = true;
        foreach (array('mode' => 'stage', 'stage' => 'live') as $param => $match) {
            $fromLive = $fromLive && strtolower((string)$this->getCachedSourceQueryParam("Versioned.$param")) == $match;
        }
        if(!$fromLive
            && !Session::get('unsecuredDraftSite')
            && !Permission::checkMember($member, array('CMS_ACCESS_LeftAndMain', 'CMS_ACCESS_CMSMain', 'VIEW_DRAFT_CONTENT'))) {
            // If we weren't definitely loaded from live, and we can't view non-live content, we need to
            // check to make sure this version is the live version and so can be viewed
            if (Versioned::get_versionnumber_by_stage($this->ClassName, 'Live', $this->ID) != $this->Version) return false;
        }

        // Orphaned pages (in the current stage) are unavailable, except for admins via the CMS
        if($this->isOrphaned()) return false;

        // Standard mechanism for accepting permission changes from extensions
        $extended = $this->extendedCan('canView', $member);
        if($extended !== null) return $extended;
        // check for empty spec
        if(!$this->CanViewType || $this->CanViewType == 'Anyone') return true;

        // check for inherit
        if($this->CanViewType == 'Inherit') {
            if($parent = $this->getParent()) return $parent->canView($member);
            else return $this->getSiteConfig()->canView($member);
        }

        // check for any logged-in users
        if($this->CanViewType == 'LoggedInUsers' && $member) {
            return true;
        }

        // check for specific groups
        if($member && is_numeric($member)) $member = DataObject::get_by_id('Member', $member);
        if(
            $this->CanViewType == 'OnlyTheseUsers'
            && $member
            && $member->inGroups($this->ViewerGroups)
        ) return true;

        return false;

    }

    public function addChild(CacheableData $data){
        $this->Children[$data->ID] = $data;
    }

    public function removeChild($childID){
        if(isset($this->Children[$childID])){
            unset($this->Children[$childID]);
        }
     }

    public function setParent(CacheableData $data){
        $this->Parent = $data;
    }

    public function getParent(){
        return $this->Parent;
    }

    public function getAllChildren(){
        return $this->Children;
    }

    public function getChildren(){
        $children = new ArrayList($this->Children);
        $children = $children->filter(
            array(
                "ShowInMenus" => 1
            )
        );
        $visible = array();
        foreach($children as $child) {
            if($child->canView()) {
                $visible[] = $child;
            }
        }
        return new ArrayList($visible);
    }

    /**
     * Return "link" or "current" depending on if this is the {@link SiteTree::isCurrent()} current page.
     *
     * @return string
     */
    public function LinkOrCurrent() {
        return $this->isCurrent() ? 'current' : 'link';
    }

    /**
     * Return "link" or "section" depending on if this is the {@link SiteTree::isSeciton()} current section.
     *
     * @return string
     */
    public function LinkOrSection() {
        return $this->isSection() ? 'section' : 'link';
    }

    public function LinkingMode(){
        if($this->isCurrent()) {
            return 'current';
        } elseif($this->isSection()) {
            return 'section';
        } else {
            return 'link';
        }
    }
    private $_cached_is_section = null;
    public function isSection() {
        if($this->_cached_is_section === null){
            if($this->isCurrent()) $this->_cached_is_section = true;
            else{
                $currentPage = Director::get_current_page();
                $navigation = $this->CachedNavigation();
                $ancestors = $navigation->getAncestores($currentPage->ID);
                $this->_cached_is_section = $currentPage instanceof SiteTree && in_array($this->ID, $ancestors->column());
            }
        }
        return $this->_cached_is_section;
    }

    public function isCurrent(){
        return $this->ID ? $this->ID == Director::get_current_page()->ID : $this === Director::get_current_page();
    }

    public function isOrphaned() {
        // Always false for root pages
       if(empty($this->ParentID)) return false;
        $parent = $this->getParent();
        return !$parent || !$parent->exists() || $parent->isOrphaned();
    }

    function debug() {
        $message = "<h3>cacheable data: ".get_class($this)."</h3>\n<ul>\n";
        $message .= "\t<li>Cached Fields:\n<ul>\n";
        foreach($this->get_cacheable_fields() as $field){
            $message .= "\t<li>$field: ". $this->$field . "</li>\n";
        }
        $message .= "</ul>\n". "</li>\n";

        $message .= "\t<li>Cached Functions:\n<ul>\n";
        foreach($this->get_cacheable_functions() as $function){
            $message .= "\t<li>$function: ". $this->$function . "</li>\n";
        }
        $message .= "</ul>\n". "</li>\n";

        if($this->Parent) {
            $message .= "\t<li>Cached Parent: ".$this->Parent->ID. ": ".$this->Parent->Title."</li>\n";
        }

        if(count($this->Children)){
            $message .= "\t<li>Cached Children:\n<ul>\n";
            foreach($this->Children as $childID => $child){
                $message .= "\t<li>Cached child: ".$childID. ": ".$child->Title."</li>\n";
            }
            $message .=  "</ul>\n</li>\n";
        }
        $message .= "</ul>\n";

        return $message;
    }

    public function Menu($level=1){
        if($nav = $this->CachedNavigation()){
            if($level == 1) {
                $root_elements = new ArrayList($nav->get_root_elements());
                $result = $root_elements->filter(array(
                    "ShowInMenus" => 1
                ));
            } else {
                $dataID = Director::get_current_page()->ID;
                $site_map = $nav->get_site_map();
                if(isset($site_map[$dataID])){
                    $parent = $site_map[$dataID];

                    $stack = array($parent);
                    if($parent){
                        while($parent = $parent->getParent()){
                            array_unshift($stack, $parent);
                        }
                    }

                    if(isset($stack[$level-2])) {
                        $elements = new ArrayList($stack[$level-2]->getAllChildren());
                        $result = $elements->filter(
                            array(
                                "ShowInMenus" => 1,
                            )
                        );
                    }
                }
            }

            $visible = array();

            // Remove all entries the can not be viewed by the current user
            // We might need to create a show in menu permission
            if(isset($result)) {
                foreach($result as $page) {
                    if($page->canView()) {
                        $visible[] = $page;
                    }
                }
            }
            return new ArrayList($visible);
        }
    }

    public function CachedNavigation()
    {
        if ($cachedNavigiation = Config::inst()->get('Cacheable', '_cached_navigation')) {
            if ($cachedNavigiation->isUnlocked() && $cachedNavigiation->get_site_config()) {
                return $cachedNavigiation;
            }

        }
    }
}