<?php
/**
 * Special page
 *
 * @file
 * @ingroup Extensions
 *
 * @author Niklas Laxström
 * @copyright Copyright © 2012 Lost in Translations Inc.
 */

class SpecialInviteSignup extends SpecialPage {
	protected $groups;

	public function __construct() {
		parent::__construct( 'InviteSignup', 'invitesignup' );
		global $wgISGroups;
		$this->groups = $wgISGroups;
	}

	public function execute( $par ) {
		$this->checkPermissions();

		$request = $this->getRequest();
		$user = $this->getUser();
		$out = $this->getOutput();
		$this->setHeaders();

		$token = $request->getVal( 'token' );
		if ( $request->wasPosted() && $user->matchEditToken( $token, 'is' ) ) {
			if ( $request->getVal( 'do' ) === 'delete' ) {
				self::deleteInvite( $request->getVal( 'hash' ) );
			}
			if ( $request->getVal( 'do' ) === 'add' ) {
				$email = $request->getVal( 'email' );
				$okay = Sanitizer::validateEmail( $email );
				if ( trim( $email ) === '' ) {
					// Silence
				} elseif ( !$okay ) {
					$out->wrapWikiMsg( Html::rawElement( 'div', array( 'class' => 'error' ), "$1" ), array( 'is-invalidemail', $email ) );
				} else {
					$groups = array();
					foreach ( $this->groups as $group ) {
						if ( $request->getCheck( "group-$group" ) ) {
							$groups[] = $group;
						}
					}
					self::addInvite( $user, $email, $groups );
				}
			}
		}

		$invites = self::getInvites();
		uasort( $invites, function ( $a, $b ) {
			return $a['when'] < $b['when'];
		} );
		$lang = $this->getLanguage();

		$out->addHtml(
			Html::openElement( 'table', array( 'class' => 'wikitable sortable' ) ) .
			Html::openElement( 'thead' ) .
			Html::openElement( 'tr' ) .
			Html::rawElement( 'th', null, $this->msg( 'is-tableth-date' )->text() ) .
			Html::rawElement( 'th', null, $this->msg( 'is-tableth-email' )->text() ) .
			Html::rawElement( 'th', null, $this->msg( 'is-tableth-inviter' )->text() ) .
			Html::rawElement( 'th', null, $this->msg( 'is-tableth-signup' )->text() ) .
			Html::rawElement( 'th', null, $this->msg( 'is-tableth-groups' )->text() ) .
			Html::rawElement( 'th', null, '' ) .
			$this->getAddRow() .
			Html::closeElement( 'thead' )
		);
		foreach ( $invites as $hash => $invite ) {
			$whenSort = array( 'data-sort-value' => $invite['when'] );
			$when = $lang->userTimeAndDate( $invite['when'], $user );
			$email = $invite['email'];
			$groups = $invite['groups'];
			if ( isset( $invite['userid'] ) ) {
				$inviteeUser = User::newFromId( $invite['userid'] );
				$name = $inviteeUser->getName();
				$email = "$name <$email>";
				$groups = $inviteeUser->getGroups();
			}

			foreach ( $groups as $i => $g ) {
				$groups[$i] = User::getGroupMember( $g );
			}
			$groups = $lang->commaList( $groups );

			$out->addHtml(
				Html::openElement( 'tr' ) .
				Html::element( 'td', $whenSort, $when ) .
				Html::element( 'td', null, $email ) .
				Html::element( 'td', null, User::newFromId( $invite['inviter'] )->getName() ) .
				Html::element( 'td', array( 'data-sort-value' => $invite['used'] ), $invite['used'] ? $lang->userTimeAndDate( $invite['used'], $user ) : '' ) .
				Html::rawElement( 'td', null, $groups ) .
				Html::rawElement( 'td', null, $invite['used'] ? '' : $this->getDeleteButton( $invite['hash'] ) ) .
				Html::closeElement( 'tr' )
			);
		}
		$out->addhtml( '</table>' );
	}

	protected function getDeleteButton( $hash ) {
		$attribs = array(
			'method' => 'post',
			'action' => $this->getTitle()->getLocalUrl(),
		);
		$form = Html::openElement( 'form', $attribs );
		$form .= Html::hidden( 'title', $this->getTitle()->getDBKey() );
		$form .= Html::hidden( 'token', $this->getUser()->getEditToken( 'is' ) );
		$form .= Html::hidden( 'hash', $hash );
		$form .= Html::hidden( 'do', 'delete' );
		$form .= Xml::submitButton( $this->msg( 'is-delete' )->text() );
		$form .= Html::closeElement( 'form' );
		return $form;
	}

	protected function getAddRow() {
		$user = $this->getUser();
		$lang = $this->getLanguage();

		$add =
			Html::hidden( 'title', $this->getTitle()->getDBKey() ) .
			Html::hidden( 'token', $user->getEditToken( 'is' ) ) .
			Html::hidden( 'do', 'add' ) .
			Xml::submitButton( $this->msg( 'is-add' )->text() );

		$attribs = array(
			'method' => 'post',
			'action' => $this->getTitle()->getLocalUrl(),
		);

		$groupChecks = array();
		foreach ( $this->groups as $group ) {
			$groupChecks[] = Xml::checkLabel( User::getGroupMember( $group ), "group-$group", "group-$group" );
		}

		$row =
			Html::openElement( 'tr' ) .
			Html::openElement( 'form', $attribs ) .
			Html::element( 'td', null, $lang->userTimeAndDate( wfTimestamp(), $user ) ) .
			Html::rawElement( 'td', null, Xml::input( 'email' ) ) .
			Html::element( 'td', null, $user->getName() ) .
			Html::element( 'td', null, '' ) .
			Html::rawElement( 'td', null, implode( ' ', $groupChecks ) ) .
			Html::rawElement( 'td', null, $add ) .
			Html::closeElement( 'form' ) .
			Html::closeElement( 'tr' );
		return $row;
	}

	public static function getInvites() {
		$dbw = wfGetDB( DB_MASTER );
		$table = 'objectcache';
		$fields = array( 'keyname' );
		$conds = array(
			'keyname' . $dbw->buildLike( wfMemcKey( 'invitesignup' ), $dbw->anyString() ),
		);
		$res = $dbw->select( $table, $fields, $conds, __METHOD__ );

		$keys = array();
		foreach ( $res as $row ) {
			$keys[] = $row->keyname;
		}

		$cache = self::getCache();
		$invites = array();
		foreach ( $cache->getMulti( $keys ) as $item ) {
			$invites[] = $item;
		}

		return $invites;
	}

	public static function addInvite( User $inviter, $email, $groups ) {
		global $wgSecretKey;
		$hash = sha1( $inviter->getId() . $wgSecretKey . $email );
		$memckey = wfMemcKey( 'invitesignup', $hash );
		$data = array(
			'inviter' => $inviter->getId(),
			'email' => $email,
			'when' => wfTimestamp(),
			'used' => false,
			'hash' => $hash,
			'groups' => $groups,
		);

		$cache = self::getCache();
		$cache->set( $memckey, $data );

		self::sendInviteEmail( $inviter, $email, $hash );
	}

	public static function sendInviteEmail( User $inviter, $email, $hash ) {
		$url = Title::newFromText( 'Special:Userlogin/signup' )->getCanonicalUrl( array( 'invite' => $hash, 'returnto' => 'Special:Dashboard' ) );

		$subj = wfMessage( 'is-emailsubj' )->inContentLanguage();
		$body = wfMessage( 'is-emailbody' )
			->params( $inviter->getName(), $url )
			->inContentLanguage();

		$emailFrom = new MailAddress( $inviter );
		$emailTo = new MailAddress( $email );
		$params = array(
			'to' => $emailTo,
			'from' => $emailFrom,
			'replyto' => $emailFrom,
			'body' =>  $body->text(),
			'subj' => $subj->text(),
		);
		$job = new EmaillingJob( Title::newMainPage(), $params );
		$job->run();
	}

	public static function deleteInvite( $hash ) {
		$memckey = wfMemcKey( 'invitesignup', $hash );
		$cache = self::getCache();
		$cache->delete( $memckey );
	}

	public static function getInvite( $hash ) {
		$memckey = wfMemcKey( 'invitesignup', $hash );
		$cache = self::getCache();
		return $cache->get( $memckey );
	}

	public static function getCache() {
		return wfGetCache( CACHE_DB );
	}

	public static function addSignupDate( User $user, $hash ) {
		$invite = self::getInvite( $hash );
		$invite['used'] = wfTimestamp();
		$invite['userid'] = $user->getId();
		$cache = self::getCache();
		$memckey = wfMemcKey( 'invitesignup', $hash );
		$cache->set( $memckey, $invite );
	}
}
