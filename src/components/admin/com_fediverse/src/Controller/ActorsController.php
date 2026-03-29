<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use NX\Component\Fediverse\Administrator\Domain\Actor\Actor;
use NX\Component\Fediverse\Administrator\Event\ActorDisabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorEnabledEvent;
use NX\Component\Fediverse\Administrator\Event\ActorProfileUpdatedEvent;
use NX\Component\Fediverse\Administrator\Helper\ActorAccountPresets;
use NX\Component\Fediverse\Administrator\Model\ActorsModel;
use NX\Component\Fediverse\Administrator\Service\Events\FediverseDomainEventDispatcherInterface;
use NX\Component\Fediverse\Administrator\Service\License\LicenseTier;
use NX\Component\Fediverse\Administrator\Service\License\LicenseService;
use NX\Component\Fediverse\Administrator\Service\Profile\ExternalProfileLinkVerifier;

/**
 * ActorsController Class
 *
 * Handle Actors requests.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorsController extends BaseController
{
    /**
     * Enable a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enable(): void
    {
        $app = $this->app;
        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );

        if ($ids === []) {
            $singleId = (int) $app->input->getInt('id', 0);
            if ($singleId > 0) {
                $ids[] = $singleId;
            }
        }

        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=actors');
            return;
        }

        $model   = $this->getActorsModel();
        $selectedIncludesLocal = false;

        foreach ($ids as $actorId) {
            $actor = $model->getById($actorId);

            if ($actor !== null && $actor->type === 'local') {
                $selectedIncludesLocal = true;
                break;
            }
        }

        if ($selectedIncludesLocal) {
            $license      = Factory::getContainer()->get(LicenseService::class);
            $currentCount = $model->countList('local');

            if ($currentCount >= $license->getTier()->actorLimit()) {
                $app->enqueueMessage(
                    Text::sprintf('COM_FEDIVERSE_ACTORS_LIMIT_REACHED', $license->getTier()->actorLimit()),
                    'warning'
                );
                $app->redirect('index.php?option=com_fediverse&view=actors');
                return;
            }
        }

        try {
            foreach ($ids as $actorId) {
                $model->enable($actorId);
            }
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new ActorEnabledEvent(
                    $ids,
                    (int) $app->getIdentity()->id
                )
            );
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_ENABLE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=actors');
    }

    /**
     * Redirect to the actor edit form.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function edit(): void
    {
        $app     = $this->app;
        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );

        $actorId = (int) $app->input->getInt('id', 0);

        if ($actorId <= 0 && $ids !== []) {
            $actorId = (int) ($ids[0] ?? 0);
        }

        if ($actorId <= 0) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=actors');
            return;
        }

        $app->redirect('index.php?option=com_fediverse&view=actor&id=' . $actorId);
    }

    /**
     * Save actor_type and object_type for a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save(): void
    {
        $this->saveProfile(false);
    }

    /**
     * Apply actor_type and object_type and stay on the edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function apply(): void
    {
        $this->saveProfile(true);
    }

    /**
     * Cancel actor edit and return to actors list.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function cancel(): void
    {
        $this->app->redirect('index.php?option=com_fediverse&view=actors');
    }

    /**
     * Close actor edit and return to actors list.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function close(): void
    {
        $this->cancel();
    }

    /**
     * Save actor edit and continue with next item.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function save2new(): void
    {
        $this->save();
    }

    /**
     * Disable a local actor.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disable(): void
    {
        $app = $this->app;
        Session::checkToken('request') or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $ids = array_map(
            'intval',
            (array) $app->input->post->get('cid', [], 'array')
        );

        if ($ids === []) {
            $singleId = (int) $app->input->getInt('id', 0);
            if ($singleId > 0) {
                $ids[] = $singleId;
            }
        }

        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        if ($ids === []) {
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_LIST_NO_SELECTION'), 'warning');
            $app->redirect('index.php?option=com_fediverse&view=actors');
            return;
        }

        try {
            $model = $this->getActorsModel();
            foreach ($ids as $actorId) {
                $model->disable($actorId);
            }
            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new ActorDisabledEvent(
                    $ids,
                    (int) $app->getIdentity()->id
                )
            );
            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_DISABLE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $app->redirect('index.php?option=com_fediverse&view=actors');
    }

    /**
     * Load the actors model for toolbar tasks.
     *
     * @return  ActorsModel
     *
     * @since  __DEPLOY_VERSION__
     */
    private function getActorsModel(): ActorsModel
    {
        /** @var ActorsModel|false $model */
        $model = $this->getModel('Actors', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof ActorsModel) {
            throw new \RuntimeException('Unable to load actors model.');
        }

        return $model;
    }

    /**
     * Save actor account identity settings.
     *
     * Persist semantic account settings and redirect either to the list or back to the editor.
     *
     * @params bool $stayOnEdit True to remain on the edit view.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function saveProfile(bool $stayOnEdit): void
    {
        $app = $this->app;
        Session::checkToken() or $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');

        $actorId      = (int) $app->input->post->getInt('id', 0);
        $accountKind  = $app->input->post->getString('account_kind', '');
        $postedType   = $app->input->post->getString('actor_type', 'Person');
        $objectType   = $app->input->post->getString('object_type', 'Note');
        $profileData  = $this->buildProfileDataFromRequest();
        $redirectUrl  = $stayOnEdit
            ? 'index.php?option=com_fediverse&view=actor&id=' . $actorId
            : 'index.php?option=com_fediverse&view=actors';

        if ($accountKind === '') {
            $accountKind = ActorAccountPresets::kindForActorType($postedType);
        }

        $actorType = ActorAccountPresets::actorTypeForKind($accountKind);

        if ($accountKind === 'person' && in_array($postedType, ActorAccountPresets::allowedActorTypes(), true)) {
            $actorType = $postedType;
            $accountKind = ActorAccountPresets::kindForActorType($actorType);
        }

        try {
            if (ActorAccountPresets::requiresPro($accountKind)) {
                Factory::getContainer()->get(LicenseService::class)->requireTier(LicenseTier::Pro);
            }

            $model = $this->getActorsModel();
            $actor = $model->getById($actorId);

            $this->assertFeaturedContentSelectionIsValid($model, $actor, $profileData);
            $profileData = $this->applyWebsiteVerificationState($profileData, $actor);

            $model->updateProfileSettings(
                $actorId,
                $actorType,
                $objectType,
                $profileData === [] ? null : (string) json_encode($profileData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            Factory::getContainer()->get(FediverseDomainEventDispatcherInterface::class)->dispatch(
                new ActorProfileUpdatedEvent(
                    $actorId,
                    $actor?->preferredUsername ?? (string) $actorId,
                    $accountKind,
                    $actorType,
                    $objectType,
                    $profileData,
                    (int) $app->getIdentity()->id
                )
            );

            $app->enqueueMessage(Text::_('COM_FEDIVERSE_ACTORS_SAVE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $message = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : Text::_('COM_FEDIVERSE_ACTORS_SAVE_ERROR');

            $app->enqueueMessage($message, 'error');
        }

        $app->redirect($redirectUrl);
    }

    /**
     * Build canonical actor profile data from the request payload.
     *
     * @return  array<string, string>  Normalized profile data.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function buildProfileDataFromRequest(): array
    {
        $profileData = [
            'name' => trim((string) $this->app->input->post->getString('profile_name', '')),
            'summary' => trim((string) $this->app->input->post->getString('profile_summary', '')),
            'url' => trim((string) $this->app->input->post->getString('profile_url', '')),
            'icon_url' => trim((string) $this->app->input->post->getString('profile_icon_url', '')),
            'image_url' => trim((string) $this->app->input->post->getString('profile_header_url', '')),
            'metadata_1_label' => trim((string) $this->app->input->post->getString('profile_metadata_1_label', '')),
            'metadata_1_value' => trim((string) $this->app->input->post->getString('profile_metadata_1_value', '')),
            'metadata_2_label' => trim((string) $this->app->input->post->getString('profile_metadata_2_label', '')),
            'metadata_2_value' => trim((string) $this->app->input->post->getString('profile_metadata_2_value', '')),
            'metadata_3_label' => trim((string) $this->app->input->post->getString('profile_metadata_3_label', '')),
            'metadata_3_value' => trim((string) $this->app->input->post->getString('profile_metadata_3_value', '')),
            'featured_content_id' => (string) max(0, (int) $this->app->input->post->getInt('featured_content_id', 0)),
        ];

        return array_filter(
            $profileData,
            static fn(string $value): bool => $value !== ''
        );
    }

    /**
     * Apply website verification state to the submitted profile data.
     *
     * Verify the actor-owned website link through reciprocal rel=me proof and persist the result.
     *
     * @params array<string, string> $profileData Submitted profile data.
     * @params ?Actor $actor Local actor being updated.
     *
     * @return  array<string, string>  Normalized profile data with verification state.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function applyWebsiteVerificationState(array $profileData, ?Actor $actor): array
    {
        unset($profileData['url_verified_at']);

        $websiteUrl = trim((string) ($profileData['url'] ?? ''));
        $actorUrl   = trim((string) ($actor?->uri ?? ''));

        if ($websiteUrl === '' || $actorUrl === '') {
            return $profileData;
        }

        /** @var ExternalProfileLinkVerifier $verifier */
        $verifier = Factory::getContainer()->get(ExternalProfileLinkVerifier::class);

        if ($verifier->verifyOwnership($websiteUrl, $actorUrl)) {
            $profileData['url_verified_at'] = gmdate('c');
        }

        return $profileData;
    }

    /**
     * Validate the selected featured intro content against the actor owner.
     *
     * Reject a selected content item when it is not a published article of the actor owner.
     *
     * @params ActorsModel $model Actor model.
     * @params ?Actor $actor Local actor being updated.
     * @params array<string, string> $profileData Submitted profile data.
     *
     * @return  void  None.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function assertFeaturedContentSelectionIsValid(ActorsModel $model, ?Actor $actor, array $profileData): void
    {
        $featuredContentId = (int) ($profileData['featured_content_id'] ?? 0);

        if ($featuredContentId <= 0) {
            return;
        }

        $userId = (int) ($actor?->userId ?? 0);

        if (!$model->canFeatureContent($userId, $featuredContentId)) {
            throw new \RuntimeException(Text::_('COM_FEDIVERSE_ACTORS_FEATURED_CONTENT_INVALID'));
        }
    }
}
