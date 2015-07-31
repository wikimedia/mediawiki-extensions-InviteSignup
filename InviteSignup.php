<?php
if ( !defined( 'MEDIAWIKI' ) ) die();
/**
 * An extension that allows users to invite new users to signup to a closed wiki.
 *
 * @file
 * @ingroup Extensions
 *
 * @author Niklas Laxström
 * @copyright Copyright © 2012-2013 Lost in Translations Inc.
 * @license GPL-2.0+
 */

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'InviteSignup',
	'version' => '2015-07-31',
	'url' => 'https://www.mediawiki.org/wiki/Extension:InviteSignup',
	'author' => array( 'Niklas Laxström' ),
	'descriptionmsg' => 'is-desc',
);

$dir = __DIR__;
$wgAutoloadClasses['InviteSignupHooks'] = "$dir/InviteSignupHooks.php";
$wgAutoloadClasses['InviteStore'] = "$dir/InviteStore.php";
$wgAutoloadClasses['SpecialInviteSignup'] = "$dir/SpecialInviteSignup.php";
$wgMessagesDirs['InviteSignup'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['InviteSignup'] = "$dir/InviteSignup.i18n.php";
$wgExtensionMessagesFiles['InviteSignupAlias'] = "$dir/InviteSignup.alias.php";
$wgSpecialPages['InviteSignup'] = 'SpecialInviteSignup';
$wgAvailableRights[] = 'invitesignup';

$wgInviteSignupHash = null;

/**
 * List of groups the invitee will be promoted automatically.
 */
$wgISGroups = array();

$wgHooks['BeforeInitialize'][] = 'InviteSignupHooks::onBeforeInitialize';
$wgHooks['UserGetRights'][] = 'InviteSignupHooks::onUserGetRights';
$wgHooks['UserCreateForm'][] = 'InviteSignupHooks::onUserCreateForm';
$wgHooks['AddNewAccount'][] = 'InviteSignupHooks::onAddNewAccount';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'InviteSignupHooks::onLoadExtensionSchemaUpdates';
