<?php declare(strict_types=1);

/**
 * @package         pkg_fediverse
 * @subpackage      com_fediverse
 *
 * @copyright   (C) 2026 BSDS / nibra Consulting <https://code.nibra.net>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace NX\Component\Fediverse\Administrator\Helper;

/**
 * ActorProfileDocumentDecorator Class
 *
 * Apply stored actor profile data to ActivityPub actor documents.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ActorProfileDocumentDecorator
{
    /**
     * Apply profile data to an actor document payload.
     *
     * Populate common profile presentation fields and labeled metadata attachments.
     *
     * @param   array<string, mixed>   $document     Actor document under construction.
     * @param   array<string, string>  $profileData  Canonical scalar profile data.
     *
     * @return  array<string, mixed>  Decorated actor document payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function applyProfileData(array $document, array $profileData): array
    {
        foreach (['name', 'summary', 'url'] as $field) {
            if (!empty($profileData[$field])) {
                $document[$field] = $profileData[$field];
            }
        }

        if (!empty($profileData['icon_url'])) {
            $document['icon'] = [
                'type' => 'Image',
                'url' => $profileData['icon_url'],
            ];
        }

        if (!empty($profileData['image_url'])) {
            $document['image'] = [
                'type' => 'Image',
                'url' => $profileData['image_url'],
            ];
        }

        $attachments = [];
        $websiteAttachment = self::buildWebsiteAttachment($profileData);

        if ($websiteAttachment !== null) {
            $attachments[] = $websiteAttachment;
        }

        $attachments = [...$attachments, ...self::buildMetadataAttachments($profileData)];

        if ($attachments !== []) {
            $document['attachment'] = $attachments;
        }

        $featuredContentId = (int) ($profileData['featured_content_id'] ?? 0);
        $documentId = trim((string) ($document['id'] ?? ''));

        if ($featuredContentId > 0 && $documentId !== '') {
            $document['featured'] = rtrim($documentId, '/') . '/featured';
        }

        return $document;
    }

    /**
     * Build the website attachment used for link verification.
     *
     * Render the actor-owned website as a rel=me property value attachment.
     *
     * @param   array<string, string>  $profileData  Canonical scalar profile data.
     *
     * @return  ?array<string, string>  ActivityPub website attachment or null.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function buildWebsiteAttachment(array $profileData): ?array
    {
        $websiteUrl = trim((string) ($profileData['url'] ?? ''));

        if ($websiteUrl === '') {
            return null;
        }

        $label = trim((string) ($profileData['url_verified_at'] ?? '')) !== ''
            ? 'Website (Verified)'
            : 'Website';

        return [
            'type' => 'PropertyValue',
            'name' => $label,
            'value' => sprintf(
                '<a href="%s" rel="me">%s</a>',
                htmlspecialchars($websiteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($websiteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            ),
        ];
    }

    /**
     * Build ActivityPub property-value attachments from stored profile metadata.
     *
     * Read the fixed metadata slots and turn complete label/value pairs into attachments.
     *
     * @param   array<string, string>  $profileData  Canonical scalar profile data.
     *
     * @return  list<array<string, string>>  ActivityPub attachment entries.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function buildMetadataAttachments(array $profileData): array
    {
        $attachments = [];

        for ($index = 1; $index <= 3; $index++) {
            $label = trim((string) ($profileData['metadata_' . $index . '_label'] ?? ''));
            $value = trim((string) ($profileData['metadata_' . $index . '_value'] ?? ''));

            if ($label === '' || $value === '') {
                continue;
            }

            $attachments[] = [
                'type' => 'PropertyValue',
                'name' => $label,
                'value' => $value,
            ];
        }

        return $attachments;
    }
}
