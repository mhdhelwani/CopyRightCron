<?php

ilCopyRightCronPlugin::getInstance()->includeClass('log/class.ilCopyRightCronLog.php');

class copyrightCronHelper
{
    /**
     * Get all files in the repository
     * @param ilCopyRightCronLog $a_log
     */
    static function _collectFilesInRepository($a_log)
    {
        global $ilDB, $tree, $lng, $ilPluginAdmin, $rbacreview, $ilUser;

        $delete_query = "DELETE FROM cron_crnhk_files_list";

        $ilDB->manipulate($delete_query);

        $sql = "SELECT od.obj_id,od.type,od.title,oref.ref_id, od.owner FROM object_data od" .
            " JOIN object_reference oref ON(oref.obj_id = od.obj_id)" .
            " JOIN tree ON (tree.child = oref.ref_id)" .
            " AND tree.tree > " . $ilDB->quote(0, "integer");

        $res = $ilDB->query($sql);
        try {
            while ($row = $ilDB->fetchAssoc($res)) {
                switch ($row["type"]) {
                    case "file":
                        $parent_node_data = $tree->getParentNodeData($row["ref_id"]);

                        if ($parent_node_data["type"] === "root") {
                            $path = "\$txt = \$lng->txt('obj_" . $parent_node_data["type"] . "');";
                        } else {
                            $path = "\$txt = \$lng->txt('obj_" . $parent_node_data["type"] . "') . ' (" .
                                self::_buildPath(
                                    $tree,
                                    $row["ref_id"],
                                    "ref_id",
                                    true
                                ) . ")';";
                        }

                        self::_insertFile($row["obj_id"],
                            $row["ref_id"],
                            0,
                            $row["owner"],
                            $row["title"],
                            "file",
                            $parent_node_data["type"],
                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(' . " . ($parent_node_data["type"] === "root" ?
                                "\$lng->txt('obj_" . $parent_node_data["type"] . "') . ')';" :
                                "'" . $parent_node_data["title"] . "' . ')';"),
                            $path);
                        break;
                    case "mep":
                        include_once "./Modules/MediaPool/classes/class.ilObjMediaPool.php";
                        include_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";

                        $objMediaPool = new ilObjMediaPool($row["ref_id"]);
                        $mediaPoolTree = $objMediaPool->getTree();
                        $sql_mep = "SELECT DISTINCT mep_tree.*, object_data.*, mep_item.obj_id mep_obj_id " .
                            "FROM mep_tree JOIN mep_item ON (mep_tree.child = mep_item.obj_id) " .
                            " JOIN object_data ON (mep_item.foreign_id = object_data.obj_id) " .
                            " WHERE mep_tree.mep_id = " . $ilDB->quote($row["obj_id"], "integer") .
                            " AND object_data.type = " . $ilDB->quote("mob", "text");
                        $res_mep = $ilDB->query($sql_mep);

                        while ($row_mep = $ilDB->fetchAssoc($res_mep)) {
                            $mediaObject = new ilObjMediaObject($row_mep["obj_id"]);

                            $mediaItems = $mediaObject->getMediaItems();

                            foreach ($mediaItems as $mediaItem) {
                                if ($mediaItem->getLocationType() === "LocalFile") {
                                    self::_insertFile($mediaObject->getId(),
                                        $row["ref_id"],
                                        $mediaItem->getId(),
                                        $mediaObject->getOwner(),
                                        $mediaItem->getlocation(),
                                        "mob_" . $mediaItem->getPurpose(),
                                        $row["type"],
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                            $tree,
                                            $row["ref_id"],
                                            "ref_id",
                                            true) .
                                        " &raquo; " . $row["title"] . " &raquo; " .
                                        self::_buildPath($mediaPoolTree,
                                            $row_mep["mep_obj_id"],
                                            "mep_obj_id",
                                            false) .
                                        " &raquo; " . $mediaItem->getPurpose() . ")';");
                                }
                            }
                            try {
                                $dir_files = iterator_to_array(new RecursiveIteratorIterator(
                                    new RecursiveDirectoryIterator($mediaObject->getDataDirectory())));

                                foreach ($dir_files as $f => $d) {
                                    $pi = pathinfo($f);

                                    if (!is_dir($f)) {
                                        $sub_dir = str_replace(
                                            "\\",
                                            "/",
                                            substr($pi["dirname"], strlen($mediaObject->getDataDirectory())));
                                        $sub_dir = ($sub_dir ? $sub_dir : " ");

                                        self::_insertFile($mediaObject->getId(),
                                            $row["ref_id"],
                                            0,
                                            $mediaObject->getOwner(),
                                            $pi["basename"],
                                            "mob|" . $pi["basename"] . "|" . $sub_dir,
                                            $row["type"],
                                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                                $tree,
                                                $row["ref_id"],
                                                "",
                                                true) .
                                            " &raquo; " . $row["title"] . " &raquo; " .
                                            self::_buildPath(
                                                $mediaPoolTree,
                                                $row_mep["mep_obj_id"],
                                                "mep_obj_id",
                                                false) .
                                            str_replace("/", " &raquo; ", trim($sub_dir)) . ")';");
                                    }
                                }
                            } catch (Exception $e) {
                                $a_log->warn($e->getMessage());
                            }
                        }

                        include_once("./Modules/MediaPool/classes/class.ilMediaPoolPage.php");

                        $pages = ilMediaPoolPage::getAllPages("mep", $row["obj_id"]);

                        foreach ($pages as $page) {
                            if (ilMediaPoolPage::_exists($page["id"])) {
                                $mepPage = new ilMediaPoolPage($page["id"]);
                                self::_fillFileArrayFromPageContent($mepPage->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . self::_buildPath(
                                        $mediaPoolTree,
                                        $page["id"],
                                        "",
                                        false) . "'",
                                    $a_log);
                            }
                        }
                        break;
                    case "sahs":
                        include_once "./Modules/Scorm2004/classes/class.ilObjSCORM2004LearningModule.php";
                        include_once("./Modules/Scorm2004/classes/class.ilSCORM2004Page.php");

                        $objSCORM = new ilObjSCORM2004LearningModule($row["ref_id"]);
                        $sCORMPoolTree = $objSCORM->getTree();
                        $pages = ilSCORM2004Page::getAllPages("sahs", $row["obj_id"]);

                        foreach ($pages as $page) {
                            if (ilSCORM2004Page::_exists("sahs", $page["id"])) {
                                $sCORMPage = new ilSCORM2004Page($page["id"]);
                                self::_fillFileArrayFromPageContent($sCORMPage->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . self::_buildPath(
                                        $sCORMPoolTree,
                                        $page["id"],
                                        "",
                                        false) . "'",
                                    $a_log);
                            }
                        }
                        break;
                    case "blog":
                        include_once "./Modules/Blog/classes/class.ilBlogPosting.php";
                        include_once "./Modules/Blog/classes/class.ilObjBlog.php";

                        $blog = new ilObjBlog($row["ref_id"]);
                        $posts = ilBlogPosting::getAllPostings($row["obj_id"]);

                        foreach ($posts as $post) {
                            if (ilBlogPosting::_exists("blp", $post["id"])) {
                                $blogPosting = new ilBlogPosting($post["id"]);
                                self::_fillFileArrayFromPageContent($blogPosting->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . $blogPosting->getTitle() . "'",
                                    $a_log);
                            }
                        }

                        if ($blog->getImageFullPath(true)) {
                            self::_insertFile($row["obj_id"],
                                $row["ref_id"],
                                0,
                                $row["owner"],
                                $blog->getImage(),
                                "blog_banner",
                                $row["type"],
                                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                "\$lng->loadLanguageModule('blog'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                self::_buildPath($tree, $row["ref_id"], "", true) . " &raquo; ' . \$lng->txt('blog_banner') . ')';");
                        }
                        break;
                    case "poll":
                        include_once "./Modules/Poll/classes/class.ilObjPoll.php";

                        $poll = new ilObjPoll($row["ref_id"]);

                        if ($poll->getImageFullPath(true)) {
                            self::_insertFile($row["obj_id"],
                                $row["ref_id"],
                                0,
                                $row["owner"],
                                $poll->getImage(),
                                "poll_image",
                                $row["type"],
                                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                "\$lng->loadLanguageModule('poll'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                self::_buildPath($tree, $row["ref_id"], "", true) . " &raquo; ' . \$lng->txt('poll_image') . ')' ;");
                        }
                        break;
                    case "crs":
                        include_once "./Modules/Course/classes/class.ilCourseObjective.php";
                        include_once "./Modules/Course/classes/Objectives/class.ilLOPage.php";
                        include_once "./Modules/Course/classes/class.ilCourseFile.php";

                        $objectiveIds = ilCourseObjective::_getObjectiveIds($row["obj_id"]);

                        foreach ($objectiveIds as $objective_id) {
                            if (ilLOPage::_exists("lobj", $objective_id)) {
                                $loPage = new ilLOPage($objective_id);
                                $loTitle = ilCourseObjective::lookupObjectiveTitle($objective_id);
                                self::_fillFileArrayFromPageContent($loPage->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . $loTitle . "'",
                                    $a_log);
                            }
                        }

                        $courseInfoFiles = ilCourseFile::_readFilesByCourse($row["obj_id"]);

                        foreach ($courseInfoFiles as $courseInfoFile) {
                            self::_insertFile($row["obj_id"],
                                $row["ref_id"],
                                $courseInfoFile->getFileId(),
                                $row["owner"],
                                $courseInfoFile->getFileName(),
                                "crs",
                                $row["type"],
                                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                "\$lng->loadLanguageModule('crs'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                self::_buildPath($tree, $row["ref_id"], "", true) . " &raquo; ' . \$lng->txt('crs_info_settings') . ')';");
                        }

                        // get course content page
                        include_once "./Services/Container/classes/class.ilContainerPage.php";

                        if (ilContainerPage::_exists("cont", $row["obj_id"])) {
                            $copPage = new ilContainerPage($row["obj_id"]);

                            self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                                $row["type"],
                                $row["ref_id"],
                                $row["title"],
                                "\$copyRightPlugin->txt('content_page')",
                                $a_log);
                        }

                        // get course content start page
                        include_once "./Services/Container/classes/class.ilContainerStartObjectsPage.php";

                        if (ilContainerPage::_exists("cstr", $row["obj_id"])) {
                            $copPage = new ilContainerStartObjectsPage($row["obj_id"]);

                            self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                                $row["type"],
                                $row["ref_id"],
                                $row["title"],
                                "\$copyRightPlugin->txt('content_start_page')",
                                $a_log);
                        }
                        break;
                    case "fold":
                        // get folder content page
                        include_once "./Services/Container/classes/class.ilContainerPage.php";

                        if (ilContainerPage::_exists("cont", $row["obj_id"])) {
                            $copPage = new ilContainerPage($row["obj_id"]);

                            self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                                $row["type"],
                                $row["ref_id"],
                                $row["title"],
                                "\$copyRightPlugin->txt('content_page')",
                                $a_log);
                        }
                        break;
                    case "cat":
                        // get category content page
                        include_once "./Services/Container/classes/class.ilContainerPage.php";

                        if (ilContainerPage::_exists("cont", $row["obj_id"])) {
                            $copPage = new ilContainerPage($row["obj_id"]);

                            self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                                $row["type"],
                                $row["ref_id"],
                                $row["title"],
                                "\$copyRightPlugin->txt('content_page')",
                                $a_log);
                        }
                        break;
                    case "htlm":
                        require_once "./Modules/HTMLLearningModule/classes/class.ilObjFileBasedLM.php";

                        $lmsObj = new ilObjFileBasedLM($row["ref_id"]);

                        try {
                            $dir_files = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lmsObj->getDataDirectory())));

                            foreach ($dir_files as $f => $d) {
                                $pi = pathinfo($f);

                                if (!is_dir($f)) {
                                    $sub_dir = str_replace(
                                        "\\",
                                        "/",
                                        substr($pi["dirname"], strlen($lmsObj->getDataDirectory())));
                                    $sub_dir = ($sub_dir ? $sub_dir : " ");

                                    self::_insertFile($row["obj_id"],
                                        $row["ref_id"],
                                        0,
                                        $row["owner"],
                                        $pi["basename"],
                                        "lms_html_file|" . $pi["basename"] . "|" . $sub_dir,
                                        $row["type"],
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                        self::_buildPath($tree, $row["ref_id"], "", true) . ")';");
                                }
                            }
                        } catch (Exception $e) {
                            $a_log->warn($e->getMessage());
                        }
                        break;
                    case "grp":
                        // get group content page
                        include_once "./Services/Container/classes/class.ilContainerPage.php";

                        if (ilContainerPage::_exists("cont", $row["obj_id"])) {
                            $copPage = new ilContainerPage($row["obj_id"]);

                            self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                                $row["type"],
                                $row["ref_id"],
                                $row["title"],
                                "\$copyRightPlugin->txt('content_page')",
                                $a_log);
                        }
                        break;
                    case "glo":
                        include_once "./Modules/Glossary/classes/class.ilGlossaryTerm.php";
                        include_once "./Modules/Glossary/classes/class.ilGlossaryDefinition.php";

                        $glossaryTerms = ilGlossaryTerm::getTermList($row["obj_id"]);

                        foreach ($glossaryTerms as $glossaryTerm) {
                            $defs = ilGlossaryDefinition::getDefinitionList($glossaryTerm["id"]);
                            foreach ($defs as $def) {
                                if (ilGlossaryDefPage::_exists("gdf", $def["id"])) {
                                    $glossaryDefPage = new ilGlossaryDefPage($def["id"]);
                                    self::_fillFileArrayFromPageContent(
                                        $glossaryDefPage->getXMLContent(),
                                        $row["type"],
                                        $row["ref_id"],
                                        $row["title"],
                                        "'" . $glossaryTerm["term"] . "'",
                                        $a_log);
                                }
                            }
                        }
                        break;
                    case "dcl":
                        include_once "./Modules/DataCollection/classes/class.ilObjDataCollection.php";
                        include_once "./Modules/DataCollection/classes/class.ilDataCollectionRecordViewViewdefinition.php";

                        $dclObj = new ilObjDataCollection($row["ref_id"]);
                        $tables = $dclObj->getTables();

                        foreach ($tables as $table) {
                            if (ilDataCollectionRecordViewViewdefinition::_exists("dclf", $table->getId())) {
                                $dclRecode = ilDataCollectionRecordViewViewdefinition::getInstanceByTableId($table->getId());
                                self::_fillFileArrayFromPageContent(
                                    $dclRecode->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . $table->getTitle() . "'",
                                    $a_log);

                                $fileFieldFound = false;
                                $tableFields = $table->getFields();
                                foreach ($tableFields as $tableField) {
                                    if (in_array($tableField->getDatatypeId(), [ilDataCollectionDatatype::INPUTFORMAT_FILE,
                                        ilDataCollectionDatatype::INPUTFORMAT_MOB])) {
                                        $fileFieldFound = true;
                                        break;
                                    }
                                }

                                if ($fileFieldFound) {
                                    foreach ($table->getRecordsByFilter() as $record) {
                                        foreach ($table->getVisibleFields() as $field) {
                                            if (in_array($field->getDatatypeId(), [ilDataCollectionDatatype::INPUTFORMAT_FILE,
                                                ilDataCollectionDatatype::INPUTFORMAT_MOB])) {
                                                $sqlFile = "SELECT od.obj_id,od.type,od.title, od.owner FROM object_data od";

                                                $sqlFile .= " LEFT JOIN usr_data ud ON (ud.usr_id = od.owner)" .
                                                    " WHERE (od.owner < " . $ilDB->quote(1, "integer") .
                                                    " OR od.owner IS NULL OR ud.login IS NULL)" .
                                                    " AND od.owner <> " . $ilDB->quote(-1, "integer");

                                                $sqlFile .= " AND od.obj_id = " .
                                                    $ilDB->quote($record->getRecordFieldValue($field->getId()), "integer");
                                                $resFile = $ilDB->query($sqlFile);

                                                while ($rowFile = $ilDB->fetchAssoc($resFile)) {
                                                    self::_insertFile($row["obj_id"],
                                                        $row["ref_id"],
                                                        0,
                                                        $row["owner"],
                                                        $rowFile["title"],
                                                        "file",
                                                        $row["type"],
                                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                                        self::_buildPath($tree, $row["ref_id"], "", true) .
                                                        " &raquo; " . $table->getTitle() . " . ')'';");
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        break;
                    case "lm":
                        include_once("./Modules/LearningModule/classes/class.ilLMPage.php");
                        include_once("./Modules/LearningModule/classes/class.ilObjLearningModule.php");

                        $pages = ilLMPage::getAllPages("lm", $row["obj_id"]);
                        $lmModule = new  ilObjLearningModule($row["ref_id"]);

                        foreach ($pages as $page) {
                            if (ilLMPage::_exists("lm", $page["id"])) {
                                $lmPage = new ilLMPage($page["id"]);
                                $title = self::_buildPath(
                                    $lmModule->getTree(),
                                    $page["id"],
                                    "",
                                    false);

                                self::_fillFileArrayFromPageContent($lmPage->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . $title . "'",
                                    $a_log);
                            }
                        }
                        break;
                    case "qpl":
                        require_once "./Modules/TestQuestionPool/classes/class.ilAssQuestionList.php";

                        $questionList = new ilAssQuestionList($ilDB, $lng, $ilPluginAdmin);
                        $questionList->setParentObjId($row["obj_id"]);
                        $questionList->load();
                        $questions = $questionList->getQuestionDataArray();

                        foreach ($questions as $question) {
                            self::_getQuestionFile($question,
                                $row,
                                $a_log,
                                $tree);
                        }
                        break;
                    case "tst":
                        require_once "./Modules/Test/classes/class.ilObjTest.php";

                        $objTest = new ilObjTest($row["ref_id"]);
                        $questions = $objTest->getTestQuestions();

                        foreach ($questions as $question) {
                            self::_getQuestionFile($question,
                                $row,
                                $a_log,
                                $tree,
                                $objTest);
                        }
                        break;
                    case "wiki":
                        require_once "./Modules/Wiki/classes/class.ilWikiPage.php";

                        $wikiPages = ilWikiPage::getAllPages($row["obj_id"]);

                        foreach ($wikiPages as $wikiPage) {
                            if (ilWikiPage::_exists("wpg", $wikiPage["id"])) {
                                $page = new ilWikiPage($wikiPage["id"]);

                                self::_fillFileArrayFromPageContent($page->getXMLContent(),
                                    $row["type"],
                                    $row["ref_id"],
                                    $row["title"],
                                    "'" . $page->getTitle() . "'",
                                    $a_log);
                            }
                        }
                        break;
                    case "bibl":
                        require_once "./Modules/Bibliographic/classes/class.ilObjBibliographic.php";

                        $biblObj = new ilObjBibliographic($row["obj_id"]);
                        self::_insertFile($row["obj_id"],
                            $row["ref_id"],
                            0,
                            $row["owner"],
                            $biblObj->getFilename(),
                            "bibl",
                            $row["type"],
                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                            self::_buildPath($tree, $row["ref_id"], "", true) . ")';");
                        break;
                    case "book":
                        require_once "./Modules/BookingManager/classes/class.ilBookingObject.php";

                        $bookObjs = ilBookingObject::getList($row["obj_id"]);

                        foreach ($bookObjs as $bookObj) {
                            if ($bookObj["info_file"]) {
                                self::_insertFile($row["obj_id"],
                                    $row["ref_id"],
                                    $bookObj["booking_object_id"],
                                    $row["owner"],
                                    $bookObj["info_file"],
                                    "book_info_file",
                                    $row["type"],
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                        $tree,
                                        $row["ref_id"],
                                        "",
                                        true) .
                                    " &raquo; " . $bookObj["title"] .
                                    " &raquo; ' . \$copyRightPlugin->txt('information_file') . ')';");
                            }

                            if ($bookObj["post_file"]) {
                                self::_insertFile($row["obj_id"],
                                    $row["ref_id"],
                                    $bookObj["booking_object_id"],
                                    $row["owner"],
                                    $bookObj["post_file"],
                                    "book_post_file",
                                    $row["type"],
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                        $tree,
                                        $row["ref_id"],
                                        "",
                                        true) .
                                    " &raquo; " . $bookObj["title"] .
                                    " &raquo; ' . \$copyRightPlugin->txt('post_file') . ')';");
                            }
                        }
                        break;
                    case "exc":
                        require_once "./Modules/Exercise/classes/class.ilExAssignment.php";
                        require_once "./Modules/Exercise/classes/class.ilExSubmission.php";
                        require_once "./Modules/Exercise/classes/class.ilObjExercise.php";

                        $exAssignments = ilExAssignment::getAssignmentDataOfExercise($row["obj_id"]);
                        $exerciseObj = new ilObjExercise($row["ref_id"]);

                        foreach ($exAssignments as $exAssignment) {
                            $exAssignmentObj = new ilExAssignment($exAssignment["id"]);

                            if ($exAssignment["fb_file"]) {
                                self::_insertFile($row["obj_id"],
                                    $row["ref_id"],
                                    $exAssignment["id"],
                                    $exerciseObj->getOwner(),
                                    $exAssignment["fb_file"],
                                    "exc_global_feedback_file",
                                    $row["type"],
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                    "\$lng->loadLanguageModule('exc'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                    self::_buildPath(
                                        $tree,
                                        $row["ref_id"],
                                        "",
                                        true) .
                                    " &raquo; " . $exAssignment["title"] .
                                    " &raquo; ' . \$lng->txt('exc_global_feedback_file') . ')';");
                            }

                            $files = $exAssignmentObj->getFiles();

                            if (count($files) > 0) {
                                foreach ($files as $file) {
                                    self::_insertFile($row["obj_id"],
                                        $row["ref_id"],
                                        $exAssignment["id"],
                                        $exerciseObj->getOwner(),
                                        $file["name"],
                                        "exc_instruction_files|" . $file["name"],
                                        $row["type"],
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$lng->loadLanguageModule('exc'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                        self::_buildPath(
                                            $tree,
                                            $row["ref_id"],
                                            "",
                                            true) .
                                        " &raquo; " . $exAssignment["title"] .
                                        " &raquo; ' . \$lng->txt('exc_instruction_files') . ')';");
                                }
                            }

                            $memberList = $exAssignmentObj->getMemberListData();

                            foreach ($memberList as $member) {
                                $exSubmission = new ilExSubmission($exAssignmentObj, $member["usr_id"]);

                                if ($exSubmission->getPeerReview()) {
                                    $criteriaCatalogueItems = $exAssignmentObj->getPeerReviewCriteriaCatalogueItems();
                                    $peerReviews = $exSubmission->getPeerReview()->getPeerReviewsByGiver($member["usr_id"]);

                                    foreach ($criteriaCatalogueItems as $item) {
                                        if (is_a($item, "ilExcCriteriaFile")) {
                                            foreach ($peerReviews as $peerReview) {
                                                $item->setPeerReviewContext(
                                                    $exAssignmentObj,
                                                    $peerReview["giver_id"],
                                                    $peerReview["peer_id"]);
                                                $files = $item->getFiles();

                                                foreach ($files as $file) {
                                                    $file_name = basename($file);

                                                    self::_insertFile($row["obj_id"],
                                                        $row["ref_id"],
                                                        $exAssignment["id"],
                                                        $member["usr_id"],
                                                        $file_name,
                                                        "peer_feedback|" . $file_name . "|" . $item->getId() . "|" .
                                                        $peerReview["peer_id"],
                                                        $row["type"],
                                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                                        self::_buildPath(
                                                            $tree,
                                                            $row["ref_id"],
                                                            "",
                                                            true) .
                                                        " &raquo; " . $exAssignment["title"] .
                                                        " &raquo; ' . \$copyRightPlugin->txt('peer_feedback') . ')';");
                                                }
                                            }
                                        }
                                    }
                                }

                                $submissionFiles = $exSubmission->getFiles();

                                foreach ($submissionFiles as $submissionFile) {
                                    self::_insertFile($row["obj_id"],
                                        $row["ref_id"],
                                        $exAssignment["id"],
                                        $member["usr_id"],
                                        $submissionFile["filetitle"],
                                        "exc_return|" . $submissionFile["filetitle"] . "|" . $submissionFile["returned_id"],
                                        $row["type"],
                                        "\$txt =\$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$lng->loadLanguageModule('exc'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                        self::_buildPath(
                                            $tree,
                                            $row["ref_id"],
                                            "",
                                            true) .
                                        " &raquo; " . $exAssignment["title"] .
                                        " &raquo; ' . \$lng->txt('exc_submission') . ')';");
                                }

                                require_once "./Modules/Exercise/classes/class.ilFSStorageExercise.php";

                                $storage = new ilFSStorageExercise($exAssignmentObj->getExerciseId(), $exAssignmentObj->getId());
                                $feed_back_files = $storage->getFeedbackFiles($member["usr_id"]);

                                foreach ($feed_back_files as $file) {
                                    self::_insertFile($row["obj_id"],
                                        $row["ref_id"],
                                        $exAssignment["id"],
                                        $member["usr_id"],
                                        $file,
                                        "ass_feedback|" . $file . "|" . $member["usr_id"],
                                        $row["type"],
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                            $tree,
                                            $row["ref_id"],
                                            "",
                                            true) .
                                        " &raquo; " . $exAssignment["title"] .
                                        " &raquo; ' . \$copyRightPlugin->txt('exc_fb_file') . '" . " &raquo; " .
                                        $member["name"] . "[" . $member["firstname"] . "]" . ")';");
                                }
                            }
                        }
                        break;
                    case "mcst":
                        require_once "./Modules/MediaCast/classes/class.ilObjMediaCast.php";
                        require_once "./Services/News/classes/class.ilNewsItem.php";
                        require_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";

                        $mediaCastObj = new ilObjMediaCast($row["ref_id"]);
                        $mediaCastItems = $mediaCastObj->getItemsArray();

                        foreach ($mediaCastItems as $mediaCastItem) {
                            $mcst_item = new ilNewsItem($mediaCastItem["id"]);
                            $mob = new ilObjMediaObject($mcst_item->getMobId());

                            $mediaItems = $mob->getMediaItems();

                            foreach ($mediaItems as $mediaItem) {
                                if ($mediaItem->getLocationType() !== "Reference") {
                                    self::_insertFile($mob->getId(),
                                        $row["ref_id"],
                                        $mediaItem->getId(),
                                        $mob->getOwner(),
                                        $mediaItem->getlocation(),
                                        "mob_" . $mediaItem->getPurpose(),
                                        $row["type"],
                                        "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                        "\$txt = '" . self::_buildPath(
                                            $tree,
                                            $row["ref_id"],
                                            "",
                                            true) .
                                        " &raquo; " . $mediaCastItem["title"] . " &raquo; " .
                                        $mediaItem->getPurpose() . ")';");
                                }
                            }

                            if ($mob->getVideoPreviewPic()) {
                                self::_insertFile($mob->getId(),
                                    $row["ref_id"],
                                    0,
                                    $mob->getOwner(),
                                    $mob->getVideoPreviewPic(true),
                                    "mob_preview_pic",
                                    $row["type"],
                                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                    "\$lng->loadLanguageModule('mcst'); \$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" .
                                    self::_buildPath(
                                        $tree,
                                        $row["ref_id"],
                                        "",
                                        true) .
                                    " &raquo; " . $mediaCastItem["title"] . " &raquo; ' . \$lng->txt('mcst_preview_picture') . ')';");
                            }
                        }
                        break;
                    case "frm":
                        require_once "./Modules/Forum/classes/class.ilObjForum.php";
                        require_once "./Modules/Forum/classes/class.ilForumTopic.php";
                        require_once "./Modules/Forum/classes/class.ilForumPost.php";
                        require_once "./Modules/Forum/classes/class.ilFileDataForum.php";

                        $forumObj = new ilObjForum($row["ref_id"]);
                        $frm = $forumObj->Forum;
                        $frm->setForumId($forumObj->getId());
                        $frm->setForumRefId($forumObj->getRefId());
                        $frm->setMDB2Wherecondition("top_frm_fk = %s ", ["integer"], [$frm->getForumId()]);
                        $topicData = $frm->getOneTopic();
                        $threads = $frm->getAllThreads($topicData["top_pk"]);

                        foreach ($threads["items"] as $thread) {
                            /**
                             * @var $thread ilForumTopic
                             */
                            $posts = $thread->getAllPosts();

                            foreach ($posts as $key => $p) {
                                $file_obj = new ilFileDataForum($forumObj->getId(), $key);
                                $files = $file_obj->getFilesOfPost();

                                if (is_array($files) && count($files)) {
                                    $post = new ilForumPost($key);
                                    $parentPost = new ilForumPost($post->getParentId());
                                    $postPath = "";

                                    if ($parentPost->getSubject()) {
                                        $postPath = $parentPost->getSubject();
                                    }

                                    while ($parentPost->getParentId() != 0) {
                                        $parentPost = new ilForumPost($parentPost->getParentId());

                                        if ($parentPost->getSubject()) {
                                            $postPath = $parentPost->getSubject() . " &raquo; " . $postPath;
                                        }
                                    }

                                    foreach ($files as $file) {
                                        self::_insertFile($row["obj_id"],
                                            $row["ref_id"],
                                            $key,
                                            $post->getPosAuthorId(),
                                            $file["name"],
                                            "forum|" . $file["name"],
                                            $row["type"],
                                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                            "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                                                $tree,
                                                $row["ref_id"],
                                                "",
                                                true) .
                                            " &raquo; " . $thread->getSubject() . $postPath . " &raquo; " .
                                            $post->getSubject() . ")';");
                                    }
                                }
                            }
                        }
                        break;
                    default:
                        break;
                }
            }

            // Workspace Files
            include_once "./Services/PersonalWorkspace/classes/class.ilWorkspaceTree.php";

            // restrict to file and media pool object
            $types = ["file", "mep"];

            $sql = "SELECT od.obj_id,od.type,od.title,tws.parent,tws.child, od.owner FROM object_data od" .
                " JOIN object_reference_ws orefws ON(orefws.obj_id = od.obj_id)" .
                " JOIN tree_workspace tws ON (tws.child = orefws.wsp_id)" .
                " WHERE " . $ilDB->in("od.type", $types, "", "text");
            $res = $ilDB->query($sql);

            while ($row = $ilDB->fetchAssoc($res)) {
                $wsp_tree = new ilWorkspaceTree($row["owner"]);
                $file_path = self::_buildPath($wsp_tree, $row["child"], "child", true);

                self::_insertFile($row["obj_id"],
                    $row["parent"],
                    0,
                    $row["owner"],
                    $row["title"],
                    "file",
                    "wps",
                    "\$txt = 'Workspace';",
                    "\$txt = 'Workspace " . ($file_path ? " &raquo; " . $file_path : "") . "';");
            }

            // Portfolio Files
            include_once "./Modules/Portfolio/classes/class.ilPortfolioPage.php";
            include_once "./Modules/Portfolio/classes/class.ilObjPortfolio.php";
            include_once("./Services/User/classes/class.ilUserQuery.php");

            $usr_query = new ilUserQuery();

            $usr_data = $usr_query->query()["set"];

            foreach ($usr_data as $usr) {
                $portfolios = ilObjPortfolio::getPortfoliosOfUser($usr["usr_id"]);

                foreach ($portfolios as $portfolio) {
                    $portfolioPages = ilPortfolioPage::getAllPages($portfolio["id"]);

                    foreach ($portfolioPages as $portfolioPage) {
                        if (ilPortfolioPage::_exists("prtf", $portfolioPage["id"])) {
                            $poPage = new ilPortfolioPage($portfolioPage["id"]);

                            self::_fillFileArrayFromPageContent($poPage->getXMLContent(),
                                "prtf",
                                $portfolio["id"],
                                $portfolio["title"],
                                "'PortFolio &raquo; " . $portfolio["title"] . " &raquo; " . $portfolioPage["title"] . "'",
                                $a_log,
                                false);
                        }
                    }

                    if ($portfolio["img"]) {
                        self::_insertFile($portfolio["id"],
                            $portfolio["id"],
                            0,
                            $usr["usr_id"],
                            $portfolio["img"],
                            "portfolio_banner",
                            "prtf",
                            "\$txt = 'Portfolio (" . $portfolio["title"] . ")';",
                            "\$lng->loadLanguageModule('prtf'); \$txt = 'PortFolio &raquo; " . $portfolio["title"] .
                            " &raquo; ' . \$lng->txt('prtf_banner');");
                    }
                }

                $usrObj = new ilObjUser($usr["usr_id"]);
                // user profile image
                if ($usrObj->getPref("profile_image")) {
                    self::_insertFile($usr["usr_id"],
                        0,
                        0,
                        $usr["usr_id"],
                        $usrObj->getPref("profile_image"),
                        "profile_picture",
                        "prtf",
                        "\$txt = \$lng->txt('personal_data');",
                        "\$txt = \$lng->txt('personal_data');");
                }

                // mail attachment
                require_once "./Services/Mail/classes/class.ilFileDataMail.php";
                /*require_once "./Services/Mail/classes/class.ilMailBoxQuery.php";
                ilMailBoxQuery::$userId = $a_user_id;
                ilMailBoxQuery::$folderId = 6;
                var_dump(ilMailBoxQuery::_getMailBoxListData());
                die();*/

                $fileMailData = new ilFileDataMail($usr["usr_id"]);
                $emailFiles = $fileMailData->getUserFilesData();

                foreach ($emailFiles as $emailFile) {
                    self::_insertFile(0,
                        $usr["usr_id"],
                        0,
                        $usr["usr_id"],
                        $emailFile["name"],
                        "email_attachment|" . $emailFile["name"],
                        "att",
                        "\$txt = \$copyRightPlugin->txt('email_attachment');",
                        "\$txt = \$copyRightPlugin->txt('email_attachment');");
                }
            }


            // get login pages
            include_once "./Services/Authentication/classes/class.ilLoginPage.php";

            $installed = $lng->getInstalledLanguages();

            foreach ($installed as $key => $langkey) {
                if (ilLoginPage::_exists("auth", ilLanguage::lookupId($langkey))) {
                    $loginPage = new ilLoginPage(ilLanguage::lookupId($langkey));

                    self::_fillFileArrayFromPageContent($loginPage->getXMLContent(),
                        "auth",
                        18,
                        "\$lng->txt('obj_auth')",
                        "\$lng->txt('obj_auth') . ' &raquo; ' . \$lng->txt('meta_l_" . $langkey . "')",
                        $a_log,
                        false,
                        "\$lng->loadLanguageModule('meta');");
                }
            }

            // get legal notice page
            include_once "./Services/Imprint/classes/class.ilImprint.php";

            if (ilImprint::_exists("impr", 1)) {
                $lgTitle = "\$lng->txt('adm_imprint')";
                $lgPage = new ilImprint(1);

                self::_fillFileArrayFromPageContent($lgPage->getXMLContent(),
                    "impr",
                    9,
                    $lgTitle,
                    $lgTitle,
                    $a_log,
                    false,
                    "\$lng->loadLanguageModule('administration');");
            }

            // get repository content page
            include_once "./Services/Container/classes/class.ilContainerPage.php";

            if (ilContainerPage::_exists("cont", 1)) {
                $copPage = new ilContainerPage(1);

                self::_fillFileArrayFromPageContent($copPage->getXMLContent(),
                    "root",
                    1,
                    "\$copyRightPlugin->txt('content_page')",
                    "\$lng->txt('obj_root')  . ' &raquo; ' .  \$copyRightPlugin->txt('content_page')",
                    $a_log,
                    false);
            }

            // shop page
            include_once "./Services/Payment/classes/class.ilShopPage.php";
            include_once "./Services/Payment/classes/class.ilObjPaymentSettingsGUI.php";
            include_once "./Services/Payment/classes/class.ilShopInfoGUI.php";

            $pages = ilPageObject::getAllPages("shop", 0);
            $shopTitle = "\$lng->txt('pay_header')";

            foreach ($pages as $page) {
                if (ilShopPage::_exists("shop", $page["id"])) {
                    $shopPage = new ilShopPage($page["id"]);

                    if ($page["id"] === (string)ilObjPaymentSettingsGUI::CONDITIONS_EDITOR_PAGE_ID) {
                        $shopPath = $shopTitle . ". ' &raquo; ' . \$lng->txt('documents')";
                        $ref_id = 19;
                    } else if ($page["id"] === (string)ilShopInfoGUI::SHOP_PAGE_EDITOR_PAGE_ID) {
                        $shopPath = $shopTitle . ". ' &raquo; ' . \$lng->txt('shop_info')";
                        $ref_id = -1;
                    } else {
                        $shopPath = $shopTitle . " . ' &raquo; Content'";
                        $ref_id = -1;
                    }

                    self::_fillFileArrayFromPageContent($shopPage->getXMLContent(),
                        "shop",
                        $ref_id,
                        $shopTitle,
                        $shopPath,
                        $a_log,
                        false,
                        "\$lng->loadLanguageModule('payment');");
                }
            }

            // layout page
            include_once "./Services/Style/classes/class.ilPageLayoutPage.php";
            include_once "./Services/Style/classes/class.ilPageLayout.php";

            $pages = ilPageLayout::getLayoutsAsArray();
            $stysTitle = "\$lng->txt('obj_stys')";

            foreach ($pages as $page) {
                if (ilPageLayoutPage::_exists("stys", $page["id"])) {
                    $stysPage = new ilPageLayoutPage($page["layout_id"]);

                    self::_fillFileArrayFromPageContent($stysPage->getXMLContent(),
                        "stys",
                        21,
                        $stysTitle,
                        $stysTitle . ". ' &raquo;  " . $page["title"] . "'",
                        $a_log,
                        false);
                }
            }
        } catch (Exception $e) {
            $a_log->warn($e->getMessage());
        }
    }

    /**
     * Get all file items that are used within the all page and uploaded by specific user
     * @var $a_content
     *
     * @return array
     */
    private static function _collectFileItemsFromPageContent($a_content)
    {
        if ($a_content) {
            $file_ids = [];
            $doc = new DOMDocument();
            try {
                $doc->loadXML($a_content);
            } catch (Exception $e) {
                return [];
            }
            $xpath = new DOMXPath($doc);
            // file items in file list
            $nodes = $xpath->query("//FileItem/Identifier");

            foreach ($nodes as $node) {
                $id_arr = explode("_", $node->getAttribute("Entry"));
                $file_id = $id_arr[count($id_arr) - 1];

                if ($file_id > 0 && ($id_arr[1] == "" || $id_arr[1] == IL_INST_ID || $id_arr[1] == 0)) {
                    $file_ids[$file_id] = $file_id;
                }
            }

            // file items in download links
            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query("//IntLink[@Type='File']");

            foreach ($nodes as $node) {
                $t = $node->getAttribute("Target");

                if (substr($t, 0, 9) == "il__dfile") {
                    $id_arr = explode("_", $t);
                    $file_id = $id_arr[count($id_arr) - 1];
                    $file_ids[$file_id] = $file_id;
                }
            }

            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query("//IntLink[@Type='MediaObject']");

            foreach ($nodes as $node) {
                $t = $node->getAttribute("Target");

                if (substr($t, 0, 8) == "il__mob_") {
                    $id_arr = explode("_", $t);
                    $file_id = $id_arr[count($id_arr) - 1];
                    $file_ids[$file_id] = $file_id;
                }
            }

            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query("//IntLink[@Type='RepositoryItem']");

            foreach ($nodes as $node) {
                $t = $node->getAttribute("Target");

                if (substr($t, 0, 8) == "il__obj_") {
                    $id_arr = explode("_", $t);
                    $file_id = $id_arr[count($id_arr) - 1];
                    $file_id = ilObject::_lookupObjectId($file_id);
                    $file_ids[$file_id] = $file_id;
                }
            }

            $xpath = new DOMXPath($doc);
            // media objects and interactive images
            $nodes = $xpath->query("//MediaObject/MediaAlias");

            foreach ($nodes as $node) {
                $id_arr = explode("_", $node->getAttribute("OriginId"));
                $file_id = $id_arr[count($id_arr) - 1];

                if ($file_id > 0 && ($id_arr[1] == "" || $id_arr[1] == IL_INST_ID || $id_arr[1] == 0)) {
                    $file_ids[$file_id] = $file_id;
                }
            }

            // media objects and interactive images
            $nodes = $xpath->query("//InteractiveImage/MediaAlias");

            foreach ($nodes as $node) {
                $id_arr = explode("_", $node->getAttribute("OriginId"));
                $file_id = $id_arr[count($id_arr) - 1];

                if ($file_id > 0 && ($id_arr[1] == "" || $id_arr[1] == IL_INST_ID || $id_arr[1] == 0)) {
                    $file_ids[$file_id] = $file_id;
                }
            }

            return $file_ids;
        } else {
            return [];
        }
    }

    private static function _buildPath($a_tree, $a_ref_id, $a_ref_node_name, $a_with_root)
    {
        $path = "";
        $path_full = $a_tree->getPathFull($a_ref_id);

        if ($path_full) {
            foreach ($path_full as $data) {
                if ($data["parent"] === "0" && !$a_with_root) {
                    continue;
                }
                if ($a_ref_id != $data[$a_ref_node_name]) {
                    $path .= ($path ? " &raquo; " : "") . ($data["title"]);
                }
            }
        }

        return $path;
    }

    /**
     * @param $a_page_content
     * @param $row_type
     * @param $row_ref_id
     * @param $row_title
     * @param $pageTitle
     * @param $log
     * @param $build_path
     */
    private static function _fillFileArrayFromPageContent($a_page_content,
                                                          $row_type,
                                                          $row_ref_id,
                                                          $row_title,
                                                          $pageTitle,
                                                          $log,
                                                          $build_path = true,
                                                          $lngToLoad = "")
    {
        global $ilDB, $tree;

        $res_file = self::_getResultFile($a_page_content);

        while ($row_file = $ilDB->fetchAssoc($res_file)) {
            if ($row_file["type"] === "mob") {
                require_once "./Services/MediaObjects/classes/class.ilObjMediaObject.php";

                $mediaObject = new ilObjMediaObject($row_file["obj_id"]);
                $mediaItems = $mediaObject->getMediaItems();
                $overlayImages = $mediaObject->getFilesOfDirectory("overlays");
                $path = "\$lng->loadLanguageModule('content');";
                if ($lngToLoad) {
                    $path .= $lngToLoad;
                }

                foreach ($overlayImages as $overlayImage) {
                    if ($build_path) {
                        $path .= "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . self::_buildPath(
                                $tree,
                                $row_ref_id,
                                "ref_id",
                                true
                            ) . " &raquo; " . $row_title . " &raquo; ' ." . $pageTitle .
                            " . ' &raquo; " . $mediaObject->getTitle() . " &raquo; ' . \$lng->txt('cont_overlay_images') .')';";
                    } else {
                        $path .= "\$txt = " . $pageTitle . " . ' &raquo; " .
                            $mediaObject->getTitle() . " &raquo; ' . \$lng->txt('cont_overlay_image');";
                    }

                    self::_insertFile($mediaObject->getId(),
                        $row_ref_id,
                        0,
                        $row_file["owner"],
                        $overlayImage,
                        "interactive_overlay_image|" . $overlayImage,
                        $row_type,
                        ($build_path ? "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . $row_title . ")';" : $row_title),
                        $path);
                }

                $used_file_names = [];

                foreach ($mediaItems as $mediaItem) {
                    if ($mediaItem->getLocationType() === "LocalFile") {
                        $path = "";
                        if ($lngToLoad) {
                            $path .= $lngToLoad;
                        }

                        if ($build_path) {
                            $path .= "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . self::_buildPath(
                                    $tree,
                                    $row_ref_id,
                                    "ref_id",
                                    true
                                ) . " &raquo; " . $row_title . " &raquo; ' . " . $pageTitle .
                                " . ' &raquo; " . $mediaObject->getTitle() .
                                " &raquo; " . $mediaItem->getPurpose() . ")';";
                        } else {
                            $path .= "\$txt = " . $pageTitle . " . ' &raquo; " . $mediaObject->getTitle() . " &raquo; " .
                                $mediaItem->getPurpose() . "';";
                        }

                        self::_insertFile($mediaObject->getId(),
                            $row_ref_id,
                            $mediaItem->getId(),
                            $row_file["owner"],
                            $mediaItem->getlocation(),
                            "mob_" . $mediaItem->getPurpose(),
                            $row_type,
                            ($build_path ? "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . $row_title . ")';" : $row_title),
                            $path);
                    }
                    $used_file_names[] = $mediaItem->getLocation();
                }

                try {
                    $dir_files = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaObject->getDataDirectory())));

                    foreach ($dir_files as $f => $d) {
                        $pi = pathinfo($f);

                        if (!is_dir($f)) {
                            $sub_dir = str_replace(
                                "\\",
                                "/",
                                substr($pi["dirname"], strlen($mediaObject->getDataDirectory())));

                            if ($sub_dir != "/overlays" &&
                                !in_array(
                                    trim(($sub_dir ? $sub_dir . "/" : "") . $pi["basename"], "/"),
                                    $used_file_names)
                            ) {
                                $sub_dir = ($sub_dir ? $sub_dir : " ");
                                $path = "";
                                if ($lngToLoad) {
                                    $path .= $lngToLoad;
                                }

                                if ($build_path) {
                                    $path .= "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . self::_buildPath(
                                            $tree,
                                            $row_ref_id,
                                            "ref_id",
                                            true) .
                                        " &raquo; " . $row_title . " &raquo; ' . " . $pageTitle .
                                        " . ' &raquo; " . $mediaObject->getTitle() .
                                        str_replace("/", " &raquo; ", trim($sub_dir)) . ")';";
                                } else {
                                    $path .= "\$txt = " . $pageTitle . " . ' &raquo; " . $mediaObject->getTitle() .
                                        str_replace("/", " &raquo; ", trim($sub_dir)) . "';";
                                }

                                self::_insertFile($mediaObject->getId(),
                                    $row_ref_id,
                                    0,
                                    $row_file["owner"],
                                    $pi["basename"],
                                    "mob|" . $pi["basename"] . "|" . $sub_dir,
                                    $row_type,
                                    ($build_path ? "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . $row_title . ")';" : $row_title),
                                    $path);
                            }
                        }
                    }
                } catch (Exception $e) {
                    $log->warn($e->getMessage());
                }
            } else {
                $path = "";
                if ($lngToLoad) {
                    $path .= $lngToLoad;
                }

                if ($build_path) {
                    $path .= "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . self::_buildPath(
                            $tree,
                            $row_ref_id,
                            "ref_id",
                            true) .
                        " &raquo; " . $row_title . " &raquo; ' . " . $pageTitle . " . ')';";
                } else {
                    $path .= "\$txt = " . $pageTitle . ";";
                }

                self::_insertFile($row_file["obj_id"],
                    $row_ref_id,
                    0,
                    $row_file["owner"],
                    $row_file["title"],
                    "file",
                    $row_type,
                    ($build_path ? "\$txt = \$lng->txt('obj_" . $row_type . "') . '(" . $row_title . ")';" : $row_title),
                    $path);
            }
        }
    }

    /**
     * @param $question
     * @param $row
     * @param $a_log
     * @param $tree
     * @param $tstObj
     */
    private static function _getQuestionFile($question,
                                             $row,
                                             $a_log,
                                             $tree,
                                             $tstObj = null)
    {
        require_once "./Modules/TestQuestionPool/classes/class.ilAssHintPage.php";
        require_once "./Modules/TestQuestionPool/classes/feedback/class.ilAssGenFeedbackPage.php";
        require_once "./Modules/TestQuestionPool/classes/feedback/class.ilAssSpecFeedbackPage.php";
        require_once "./Modules/TestQuestionPool/classes/class.ilAssQuestionPage.php";


        if (ilAssQuestionPage::_exists("qpl", $question["id"])) {
            $questionPage = new ilAssQuestionPage($question["question_id"]);

            self::_fillFileArrayFromPageContent($questionPage->getXMLContent(),
                $row["type"],
                $row["ref_id"],
                $row["title"],
                "'" . $question["title"] . " &raquo; ' . \$copyRightPlugin->txt('question_page')",
                $a_log);
        }

        $hintPages = ilAssHintPage::getAllPages("qht", $question["question_id"]);

        foreach ($hintPages as $hintPage) {
            if (ilAssHintPage::_exists("qht", $hintPage["id"])) {
                $page = new ilAssHintPage($hintPage["id"]);

                self::_fillFileArrayFromPageContent($page->getXMLContent(),
                    $row["type"],
                    $row["ref_id"],
                    $row["title"],
                    "'" . $question["title"] . " &raquo; ' . \$lng->txt('hint')",
                    $a_log);
            }
        }

        $genFeedbackPages = ilAssGenFeedbackPage::getAllPages(
            "qfbg",
            $question["question_id"]);

        foreach ($genFeedbackPages as $genFeedbackPage) {
            if (ilAssGenFeedbackPage::_exists("qfbg", $genFeedbackPage["id"])) {
                $page = new ilAssGenFeedbackPage($genFeedbackPage["id"]);

                self::_fillFileArrayFromPageContent($page->getXMLContent(),
                    $row["type"],
                    $row["ref_id"],
                    $row["title"],
                    "'" . $question["title"] . " &raquo; ' . \$lng->txt('feedback_generic')",
                    $a_log);
            }
        }

        $specFeedbackPages = ilAssSpecFeedbackPage::getAllPages(
            "qfbs",
            $question["question_id"]);

        foreach ($specFeedbackPages as $specFeedbackPage) {
            if (ilAssSpecFeedbackPage::_exists("qfbs", $specFeedbackPage["id"])) {
                $page = new ilAssSpecFeedbackPage($specFeedbackPage["id"]);

                self::_fillFileArrayFromPageContent($page->getXMLContent(),
                    $row["type"],
                    $row["ref_id"],
                    $row["title"],
                    "'" . $question["title"] . " &raquo; ' . \$lng->txt('feedback_generic')",
                    $a_log);
            }
        }

        require_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";

        assQuestion::_includeClass($question["type_tag"]);
        $questionObj = new $question["type_tag"]($question["question_id"]);
        $questionObj->loadFromDb($question["question_id"]);
        $questionSuggestion = $questionObj->getSuggestedSolution();

        if ($questionSuggestion["type"] === "file") {
            self::_insertFile($row["obj_id"],
                $row["ref_id"],
                $question["question_id"],
                $row["owner"],
                strlen($questionSuggestion["value"]["filename"]) ?
                    $questionSuggestion["value"]["filename"] : $questionSuggestion["value"]["name"],
                "qpl_recapitulation",
                $row["type"],
                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath(
                    $tree,
                    $row["ref_id"],
                    "",
                    true) .
                " &raquo; " . $question["title"] . " &raquo; ' . \$copyRightPlugin->txt('suggested_solution') . ')';");
        }

        if ($question["type_tag"] === "assFileUpload" && $tstObj) {
            $data = $tstObj->getCompleteEvaluationData(FALSE);
            $max_pass = $tstObj->getMaxPassOfTest();

            foreach ($data->getParticipants() as $active_id => $participant) {
                for ($pass = 0; $pass < $max_pass; $pass++) {
                    $testResultData = $tstObj->getTestResult($active_id, $pass);

                    foreach ($testResultData as $questionData) {
                        if (!isset($questionData["qid"]) || $questionData["qid"] != $question["question_id"]) {
                            continue;
                        }

                        $solutionFiles = $questionObj->getUploadedFiles($active_id, $pass);

                        foreach ($solutionFiles as $solutionFile) {
                            self::_insertFile($row["obj_id"],
                                $row["ref_id"],
                                $active_id,
                                $row["owner"],
                                $solutionFile["value2"],
                                "tst_solutions|" . $solutionFile["value1"] . "|" . $solutionFile["solution_id"] . "|" .
                                $data->getTest()->getTestId() . "|" . $solutionFile["value2"] . "|" . $questionData["qid"],
                                $row["type"],
                                "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                                "\$text = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath($tree,
                                    $row["ref_id"],
                                    "",
                                    true) .
                                " &raquo; " . $question["title"] . " &raquo; ' . \$copyRightPlugin->txt('test_solution') . ' &raquo; ' .
                                \$lng->txt('toplist_col_participant') . '/' .\$lng->txt('pass') . ' [ " .
                                $participant->getName() . "/" . ($pass + 1) . "'])';");
                        }
                    }
                }
            }
        }

        if ($question["type_tag"] === "assJavaApplet") {
            if ($questionObj->getJavaAppletFilename()) {
                self::_insertFile($row["obj_id"],
                    $row["ref_id"],
                    $question["question_id"],
                    $row["owner"],
                    $questionObj->getJavaAppletFilename(),
                    "java_applet",
                    $row["type"],
                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                    "\$text = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath($tree,
                        $row["ref_id"],
                        "",
                        true) .
                    " & raquo; " . $question["title"] . " & raquo; ' . \$copyRightPlugin->txt('question_file') . ')';");
            }
        } else if ($question["type_tag"] === "assImagemapQuestion") {
            if ($questionObj->getImageFilename()) {
                self::_insertFile($row["obj_id"],
                    $row["ref_id"],
                    $question["question_id"],
                    $row["owner"],
                    $questionObj->getImageFilename(),
                    "image_map",
                    $row["type"],
                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                    "\$text = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath($tree,
                        $row["ref_id"],
                        "",
                        true) .
                    " & raquo; " . $question["title"] . " & raquo; ' . \$copyRightPlugin->txt('question_file') . ')';");
            }
        } else if ($question["type_tag"] === "assFlashQuestion") {
            if ($questionObj->getApplet()) {
                self::_insertFile($row["obj_id"],
                    $row["ref_id"],
                    $question["question_id"],
                    $row["owner"],
                    $questionObj->getApplet(),
                    "flash",
                    $row["type"],
                    "\$txt = \$lng->txt('obj_" . $row["type"] . "') . '(" . $row["title"] . ")';",
                    "\$text = \$lng->txt('obj_" . $row["type"] . "') . '(" . self::_buildPath($tree,
                        $row["ref_id"],
                        "",
                        true) .
                    " & raquo; " . $question["title"] . " & raquo; ' . \$copyRightPlugin->txt('question_file') . ')';");
            }
        }
    }

    /**
     * @param $a_page_content
     * @return mixed
     */
    private static function _getResultFile($a_page_content)
    {
        global $ilDB;

        $file_ids = self::_collectFileItemsFromPageContent($a_page_content);
        $sql_file = "SELECT od.obj_id,od.type,od.title, od.type, od.owner FROM object_data od" .
            " WHERE " . $ilDB->in("od.obj_id", $file_ids, "", "integer");
        $res_file = $ilDB->query($sql_file);

        return $res_file;
    }

    private static function _insertFile($a_obj_id,
                                        $a_ref_id,
                                        $a_sub_id,
                                        $a_owner,
                                        $a_file_title,
                                        $a_file_info,
                                        $a_parent_type,
                                        $a_parent_title,
                                        $a_path)
    {
        global $ilDB;

        $values = [
            "obj_id" => ["integer", $a_obj_id],
            "ref_id" => ["integer", $a_ref_id],
            "sub_id" => ["integer", $a_sub_id],
            "owner" => ["integer", $a_owner],
            "file_title" => ["text", $a_file_title],
            "file_info" => ["text", $a_file_info],
            "parent_type" => ["text", $a_parent_type],
            "parent_title" => ["text", $a_parent_title],
            "path" => ["text", $a_path]];

        $ilDB->insert("cron_crnhk_files_list", $values);
    }
}