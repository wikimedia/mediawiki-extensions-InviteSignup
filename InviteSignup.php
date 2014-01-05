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

$GLOBALS['wgExtensionCredits']['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'InviteSignup',
	'version' => '2013-05-22',
	'url' => 'https://www.mediawiki.org/wiki/Extension:InviteSignup',
	'author' => array( 'Niklas Laxström' ),
	'descriptionmsg' => 'is-desc',
);

$dir = __DIR__;
$GLOBALS['wgAutoloadClasses']['InviteStore'] = "$dir/InviteStore.php";
$GLOBALS['wgAutoloadClasses']['SpecialInviteSignup'] = "$dir/SpecialInviteSignup.php";
$GLOBALS['wgExtensionMessagesFiles']['InviteSignup'] = "$dir/InviteSignup.i18n.php";
$GLOBALS['wgExtensionMessagesFiles']['InviteSignupAlias'] = "$dir/InviteSignup.alias.php";
$GLOBALS['wgSpecialPages']['InviteSignup'] = 'SpecialInviteSignup';
$GLOBALS['wgAvailableRights'][] = 'invitesignup';

$GLOBALS['wgInviteSignupHash'] = null;

/**
 * List of groups the invitee will be promoted automatically.
 */
$GLOBALS['wgISGroups'] = array();

global $wgHooks;
$wgHooks['BeforeInitialize'][] = function ( $title, &$unused, &$output, &$user, $request ) {
	if ( !$title->isSpecialPage() ) {
		return true;
	}

	list( $name ) = SpecialPageFactory::resolveAlias( $title->getDBkey() );
	if ( $name !== 'Userlogin' ) {
		return true;
	}

	$hash = $request->getVal( 'invite', $request->getCookie( 'invite' ) );
	if ( $hash ) {
		$store = new InviteStore( wfGetDB( DB_SLAVE ), 'invitesignup' );
		$invite = $store->getInvite( $hash );
		if ( $invite && $invite['used'] === null ) {
			global $wgInviteSignupHash;
			$wgInviteSignupHash = $hash;
			$request->response()->setCookie( 'invite', $hash );
		}
	}

	return true;
};

$wgHooks['UserGetRights'][] = function ( $user, &$rights ) {
	global $wgInviteSignupHash;
	if ( $wgInviteSignupHash === null ) {
		return true;
	}
	$rights[] = 'createaccount';

	return true;
};

$wgHooks['UserCreateForm'][] = function ( &$template ) {
	global $wgInviteSignupHash;
	if ( $wgInviteSignupHash === null ) {
		return true;
	}
	$template->data['link'] = null;
	$template->data['useemail'] = false;

	return true;
};

$wgHooks['AddNewAccount'][] = function ( $user ) {
	global $wgInviteSignupHash;
	if ( $wgInviteSignupHash === null ) {
		return true;
	}

	$store = new InviteStore( wfGetDB( DB_MASTER ), 'invitesignup' );

	$invite = $store->getInvite( $wgInviteSignupHash );
	$user->setOption( 'is-inviter', $invite['inviter'] );
	$user->setEmail( $invite['email'] );
	$user->confirmEmail();
	foreach ( $invite['groups'] as $group ) {
		$user->addGroup( $group );
	}
	$user->saveSettings();
	$store->addSignupDate( $user, $wgInviteSignupHash );
	global $wgRequest;
	$wgRequest->response()->setCookie( 'invite', '', time() - 86400 );
	return true;
};

$wgHooks['LoadExtensionSchemaUpdates'][] = function ( DatabaseUpdater $updater ) {
	$dir = __DIR__ . '/sql';
	$updater->addExtensionTable( 'invitesignup', "$dir/invitesignup.sql" );
	return true;
};
