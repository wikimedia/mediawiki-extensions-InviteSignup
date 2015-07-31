<?php

class InviteSignupHooks {
	public static function onBeforeInitialize( $title, &$unused, &$output, &$user, $request ) {
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
	}

	public static function onUserGetRights( $user, &$rights ) {
		global $wgInviteSignupHash;
		if ( $wgInviteSignupHash === null ) {
			return true;
		}
		$rights[] = 'createaccount';
	}

	public static function onUserCreateForm( &$template ) {
		global $wgInviteSignupHash;
		if ( $wgInviteSignupHash === null ) {
			return true;
		}
		$template->data['link'] = null;
		$template->data['useemail'] = false;
	}

	public static function onAddNewAccount( $user ) {
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
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = __DIR__ . '/sql';
		$updater->addExtensionTable( 'invitesignup', "$dir/invitesignup.sql" );
	}
}