<?php
/**
 * Absolute final cleanup for remaining snake_case method calls
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

// Final round of specific method fixes
$method_call_fixes = [
    '->ensureIdentityExists(' => '->ensureIdentityExists(',
    '->generateUniqueSlug(' => '->generateUniqueSlug(',
    '->determineEventPrivacy(' => '->determineEventPrivacy(',
    '->getGuestStats(' => '->getGuestStats(',
    '->getTotalGuests(' => '->getTotalGuests(',
    '->getConfirmedGuests(' => '->getConfirmedGuests(',
    '->getEventImages(' => '->getEventImages(',
    '->validateEventData(' => '->validateEventData(',
    '->processCoverImage(' => '->processCoverImage(',
    '->sendEventNotifications(' => '->sendEventNotifications(',
    '->getUserEvents(' => '->getUserEvents(',
    '->checkUserPermissions(' => '->checkUserPermissions(',
    '->getCommunityEvents(' => '->getCommunityEvents(',
    '->updateEventStats(' => '->updateEventStats(',
    '->deleteEventData(' => '->deleteEventData(',
    '->getConversationParticipants(' => '->getConversationParticipants(',
    '->checkConversationAccess(' => '->checkConversationAccess(',
    '->getConversationReplies(' => '->getConversationReplies(',
    '->addConversationReply(' => '->addConversationReply(',
    '->updateConversationMeta(' => '->updateConversationMeta(',
    '->deleteConversationReply(' => '->deleteConversationReply(',
    '->getUserConversations(' => '->getUserConversations(',
    '->markConversationRead(' => '->markConversationRead(',
    '->getUnreadCount(' => '->getUnreadCount(',
    '->sendConversationNotification(' => '->sendConversationNotification(',
    '->getCommunityData(' => '->getCommunityData(',
    '->updateCommunityMeta(' => '->updateCommunityMeta(',
    '->getMemberCount(' => '->getMemberCount(',
    '->checkMemberStatus(' => '->checkMemberStatus(',
    '->updateMemberRole(' => '->updateMemberRole(',
    '->removeMemberFromCommunity(' => '->removeMemberFromCommunity(',
    '->getCommunityStats(' => '->getCommunityStats(',
    '->validateCommunityData(' => '->validateCommunityData(',
    '->processCommunityImage(' => '->processCommunityImage(',
    '->sendCommunityNotifications(' => '->sendCommunityNotifications(',
];

$files = findPHPFiles('/Users/lonnholiday/Repositories/vivalatable');
$total_fixes = 0;

foreach ($files as $file) {
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
        echo "Fixed $fixes_in_file method calls in: $file\n";
        $total_fixes += $fixes_in_file;
    }
}

echo "\nTotal absolute final method calls fixed: $total_fixes\n";
echo "Absolute final method cleanup completed!\n";