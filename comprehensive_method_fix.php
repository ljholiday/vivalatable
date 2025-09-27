<?php
/**
 * Comprehensive fix for ALL remaining snake_case method calls
 */

function findPHPFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

// Comprehensive map of ALL remaining method call fixes
$method_call_fixes = [
    // Ajax handler methods
    '->initRoutes(' => '->initRoutes(',
    '->getEventManager(' => '->getEventManager(',
    '->getCommunityManager(' => '->getCommunityManager(',
    '->getConversationManager(' => '->getConversationManager(',
    '->handleCoverImageUpload(' => '->handleCoverImageUpload(',
    '->getErrorMessage(' => '->getErrorMessage(',

    // Event manager methods
    '->getEventConversations(' => '->getEventConversations(',
    '->sendRsvpInvitation(' => '->sendRsvpInvitation(',

    // Community manager methods
    '->isMember(' => '->isMember(',
    '->getMemberRole(' => '->getMemberRole(',
    '->allowsAutoJoinOnReply(' => '->allowsAutoJoinOnReply(',
    '->getCommunity(' => '->getCommunity(',

    // Guest manager methods
    '->handleGuestRsvp(' => '->handleGuestRsvp(',
    '->sendRsvpInvitation(' => '->sendRsvpInvitation(',

    // Error object methods
    '->getErrorMessage(' => '->getErrorMessage(',
    '->getErrorCode(' => '->getErrorCode(',

    // Database helper functions that might be method calls
    '::isVtError(' => '::isVtError(',

    // Any WordPress-style function calls that are actually methods
    '->wpParseArgs(' => '->wpParseArgs(',
    '->wpRemoteGet(' => '->wpRemoteGet(',
    '->wpRemotePost(' => '->wpRemotePost(',

    // Profile and user methods
    '->getProfileData(' => '->getProfileData(',
    '->updateUserMeta(' => '->updateUserMeta(',
    '->getUserMeta(' => '->getUserMeta(',

    // Auth methods
    '->currentUserCan(' => '->currentUserCan(',
    '->isUserLoggedIn(' => '->isUserLoggedIn(',

    // Cache methods
    '->setCache(' => '->setCache(',
    '->getCache(' => '->getCache(',
    '->deleteCache(' => '->deleteCache(',

    // Image manager methods
    '->uploadImage(' => '->uploadImage(',
    '->deleteImageFile(' => '->deleteImageFile(',
    '->resizeImage(' => '->resizeImage(',

    // Mail methods
    '->sendEmail(' => '->sendEmail(',
    '->queueEmail(' => '->queueEmail(',

    // Configuration methods
    '->loadConfig(' => '->loadConfig(',
    '->saveConfig(' => '->saveConfig(',

    // Event form handler methods
    '->validateEventData(' => '->validateEventData(',
    '->processEventSubmission(' => '->processEventSubmission(',

    // Security methods
    '->checkPermissions(' => '->checkPermissions(',
    '->verifyToken(' => '->verifyToken(',
    '->generateHash(' => '->generateHash(',

    // Conversation methods that might have been missed
    '->getConversationById(' => '->getConversationById(',
    '->deleteReply(' => '->deleteReply(',
    '->getReply(' => '->getReply(',

    // Generic pattern fixes
    '->create' => '->create',
    '->update' => '->update',
    '->delete' => '->delete',
    '->get' => '->get',
    '->set' => '->set',
    '->send' => '->send',
    '->load' => '->load',
    '->save' => '->save',
    '->handle' => '->handle',
    '->process' => '->process',
    '->validate' => '->validate',
    '->check' => '->check',
    '->generate' => '->generate',
    '->build' => '->build',
];

$files = findPHPFiles('/Users/lonnholiday/Repositories/vivalatable');
$total_fixes = 0;

foreach ($files as $file) {
    echo "Processing: $file\n";

    $content = file_get_contents($file);
    $original_content = $content;

    foreach ($method_call_fixes as $old => $new) {
        $content = str_replace($old, $new, $content);
    }

    if ($content !== $original_content) {
        file_put_contents($file, $content);
        $fixes_in_file = 0;
        foreach ($method_call_fixes as $old => $new) {
            $fixes_in_file += substr_count($original_content, $old);
        }
        echo "  Fixed $fixes_in_file method calls\n";
        $total_fixes += $fixes_in_file;
    }
}

echo "\nTotal comprehensive method calls fixed: $total_fixes\n";
echo "Comprehensive method cleanup completed!\n";