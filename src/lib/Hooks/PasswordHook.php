<?php
/**
 * Created by PhpStorm.
 * User: marius
 * Date: 13.10.17
 * Time: 23:23
 */

namespace OCA\Passwords\Hooks;

use OCA\Passwords\Db\Password;
use OCA\Passwords\Db\PasswordRevision;
use OCA\Passwords\Db\ShareRevision;
use OCA\Passwords\Db\TagRevision;
use OCA\Passwords\Services\Object\PasswordRevisionService;
use OCA\Passwords\Services\Object\PasswordService;
use OCA\Passwords\Services\Object\PasswordTagRelationService;
use OCA\Passwords\Services\Object\ShareRevisionService;
use OCA\Passwords\Services\Object\ShareService;
use OCA\Passwords\Services\Object\TagRevisionService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Class PasswordHook
 *
 * @package OCA\Passwords\Hooks\Password
 */
class PasswordHook {

    /**
     * @var ShareService
     */
    protected $shareService;

    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * @var PasswordRevisionService
     */
    protected $revisionService;

    /**
     * @var PasswordTagRelationService
     */
    protected $relationService;

    /**
     * @var TagRevisionService
     */
    protected $tagRevisionService;

    /**
     * PasswordHook constructor.
     *
     * @param ShareService               $shareService
     * @param TagRevisionService         $tagRevisionService
     * @param PasswordRevisionService    $revisionService
     * @param PasswordTagRelationService $relationService
     */
    public function __construct(
        ShareService $shareService,
        TagRevisionService $tagRevisionService,
        PasswordRevisionService $revisionService,
        PasswordTagRelationService $relationService
    ) {
        $this->shareService       = $shareService;
        $this->revisionService    = $revisionService;
        $this->relationService    = $relationService;
        $this->tagRevisionService = $tagRevisionService;
    }

    /**
     * @param Password         $password
     * @param PasswordRevision $newRevision
     *
     * @throws DoesNotExistException
     * @throws \Exception
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function preSetRevision(Password $password, PasswordRevision $newRevision): void {
        if($password->getRevision() === null) return;
        if($password->isEditable() && ($password->getShareId() || $password->hasShares())) {
            $this->updateShares($password);
        }

        /** @var PasswordRevision $oldRevision */
        $oldRevision = $this->revisionService->findByUuid($password->getRevision());
        if($oldRevision->getHidden() != $newRevision->getHidden()) {
            $relations = $this->relationService->findByPassword($password->getUuid());

            foreach($relations as $relation) {
                /** @var TagRevision $tagRevision */
                $tagRevision = $this->tagRevisionService->findByModel($relation->getTag());
                $relation->setHidden($newRevision->isHidden() || $tagRevision->isHidden());
                $this->relationService->save($relation);
            }
        }
    }

    /**
     * @param Password $password
     *
     * @throws \Exception
     */
    protected function updateShares(Password $password): void {
        if($password->getShareId()) {
            $share = $this->shareService->findByTargetPassword($password->getUuid());
            $share->setTargetUpdated(true);
            $this->shareService->save($share);
        }

        if($password->hasShares()) {
            $shares = $this->shareService->findBySourcePassword($password->getUuid());
            foreach($shares as $share) {
                $share->setSourceUpdated(true);
                $this->shareService->save($share);
            }
        }
    }

    /**
     * @param Password $password
     *
     * @throws \Exception
    // @TODO delete shares
     */
    public function preDelete(Password $password): void {
        $relations = $this->relationService->findByPassword($password->getUuid());

        foreach($relations as $relation) {
            $this->relationService->delete($relation);
        }

        if($password->hasShares()) {
            $shares = $this->shareService->findBySourcePassword($password->getUuid());
            foreach($shares as $share) {
                $this->shareService->delete($share);
            }
            $password->setHasShares(false);
        }
    }

    /**
     * @param Password $password
     *
     * @throws \Exception
     */
    public function postDelete(Password $password): void {
        /** @var PasswordRevision[] $revisions */
        $revisions = $this->revisionService->findByModel($password->getUuid());

        foreach($revisions as $revision) {
            $this->revisionService->delete($revision);
        }

        if($password->getShareId()) {
            $share = $this->shareService->findByTargetPassword($password->getUuid());
            $this->shareService->delete($share);
        }
    }

    /**
     * @param Password $originalPassword
     * @param Password $clonedPassword
     *
     * @throws \Exception
     */
    public function postClone(Password $originalPassword, Password $clonedPassword): void {
        /** @var PasswordRevision[] $revisions */
        $revisions = $this->revisionService->findByModel($originalPassword->getUuid());

        $currentClonedRevision = null;
        foreach($revisions as $revision) {
            /** @var PasswordRevision $revisionClone */
            $revisionClone = $this->revisionService->clone($revision, ['model' => $clonedPassword->getUuid()]);
            $this->revisionService->save($revisionClone);
            if($revision->getUuid() == $originalPassword->getRevision()) {
                $clonedPassword->setRevision($revisionClone->getUuid());
                $currentClonedRevision = $revisionClone;
            }
        }

        $relations = $this->relationService->findByPassword($originalPassword->getUuid());
        foreach($relations as $relation) {
            $relationClone = $this->relationService->clone($relation, [
                'password'         => $currentClonedRevision->getModel(),
                'passwordRevision' => $currentClonedRevision->getUuid(),
                'hidden'           => $currentClonedRevision->isHidden() || $relation->isHidden()
            ]);
            $this->relationService->save($relationClone);
        }
    }
}