<?php
/**
 * Final cleanup for any remaining snake_case method calls
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

// Specific method call fixes that were missed
$method_call_fixes = [
    // Malformed method calls that need fixing
    '->sendRsvpConfirmation(' => '->sendRsvpConfirmation(',
    '->getRsvpSuccessMessage(' => '->getRsvpSuccessMessage(',
    '->createRsvpInvitation(' => '->createRsvpInvitation(',
    '->getUserId(' => '->getUserId(',
    '->createUserProfile(' => '->createUserProfile(',
    '->updateUserMeta(' => '->updateUserMeta(',
    '->getUserCommunities(' => '->getUserCommunities(',
    '->checkMemberPermissions(' => '->checkMemberPermissions(',
    '->getConversationCount(' => '->getConversationCount(',
    '->getEventConversations(' => '->getEventConversations(',
    '->processRsvpData(' => '->processRsvpData(',
    '->validateEventData(' => '->validateEventData(',
    '->sendEventNotification(' => '->sendEventNotification(',
    '->getCommunityMembers(' => '->getCommunityMembers(',
    '->addCommunityMember(' => '->addCommunityMember(',
    '->removeCommunityMember(' => '->removeCommunityMember(',
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

echo "\nTotal final method calls fixed: $total_fixes\n";
echo "Final method cleanup completed!\n";