<?php

namespace craft\cloud\fs;

use Aws\Credentials\Credentials;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use Aws\S3\S3Client;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\cloud\Helper;
use craft\cloud\Module;
use craft\errors\FsException;
use craft\flysystem\base\FlysystemFs;
use craft\fs\Local;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeInterface;
use Generator;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\Visibility;
use League\Uri\Components\HierarchicalPath;
use League\Uri\Uri;
use Throwable;
use yii\web\BadRequestHttpException;

/**
 *
 * @property-read string $bucketName
 * @property-read string $prefix
 * @property-read ?string $settingsHtml
 */
class Fs extends FlysystemFs
{
    protected static bool $showUrlSetting = false;
    protected ?string $expires = null;
    protected Local $localFs;
    protected S3Client $client;
    public ?string $subfolder = null;

    public function init(): void
    {
        parent::init();

        $this->localFs = Craft::createObject([
            'class' => Local::class,
            'hasUrls' => $this->hasUrls,
            'url' => $this->hasUrls ? '@web/cloud-fs' : null,
            'path' => '@webroot/cloud-fs',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getRootUrl(): ?string
    {
        if (!$this->hasUrls) {
            return null;
        }

        return $this->createUrl('');
    }

    /**
     * @inheritDoc
     */
    public function getRootPath(): string
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->getRootPath();
        }

        return HierarchicalPath::createFromString(
            Module::getInstance()->getConfig()->environmentId
        )->withTrailingSlash();
    }

    public function createUrl(string $path): string
    {
        $baseUrl = Helper::isCraftCloud()
            ? Module::getInstance()->getConfig()->getCdnBaseUrl()
            : $this->localFs->getRootUrl();

        return Uri::createFromBaseUri(
            $this->prefixPath($path),
            $baseUrl,
        );
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'subfolder',
            ],
        ];

        return $behaviors;
    }

    public function settingsAttributes(): array
    {
        return array_merge(parent::settingsAttributes(), ['expires']);
    }

    public function getExpires(): ?string
    {
        return $this->expires;
    }

    public function setExpires(null|string|array $expires): void
    {
        $this->expires = is_array($expires) ? $this->normalizeExpires($expires) : $expires;
    }

    public function normalizeExpires(array $expires): ?string
    {
        $amount = (int)$expires['amount'];
        $period = $expires['period'];

        if (!$amount || !$period) {
            return null;
        }

        return "$amount $period";
    }

    /**
     * @inheritDoc
     */
    protected function createAdapter(): AwsS3V3Adapter
    {
        return new AwsS3V3Adapter(
            client: $this->getClient(),
            bucket: $this->getBucketName(),
            prefix: $this->prefixPath(),
        );
    }

    protected function invalidateCdnPath(string $path): bool
    {
        // TODO: cloudflare
        return false;
    }

    protected function addFileMetadataToConfig(array $config): array
    {
        if (!empty($this->getExpires()) && DateTimeHelper::isValidIntervalString($this->getExpires())) {
            $expires = new DateTime();
            $now = new DateTime();
            $expires->modify('+' . $this->getExpires());
            $diff = (int)$expires->format('U') - (int)$now->format('U');
            $config['CacheControl'] = "max-age=$diff";
        }

        if (!$this->hasUrls) {
            $config['Tagging'] = Visibility::PRIVATE;
        }

        return parent::addFileMetadataToConfig($config);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('cloud/fsSettings', [
            'fs' => $this,
            'periods' => Assets::periodList(),
        ]);
    }

    public function prefixPath(?string $path = null): string
    {
        return HierarchicalPath::createRelativeFromSegments([
            $this->getRootPath(),
            $this->subfolder ?? '',
            $path ?? '',
        ])->withoutEmptySegments()->withoutTrailingSlash();
    }

    public function getBucketName(): ?string
    {
        return Module::getInstance()->getConfig()->projectId;
    }

    public function createCredentials(): ?Credentials
    {
        $key = Module::getInstance()->getConfig()->accessKey;

        return $key ? new Credentials(
            $key,
            Module::getInstance()->getConfig()->accessSecret,
        ) : null;
    }

    public function createClient(array $config = []): S3Client
    {
        $config = array_merge(
            [
                'region' => Module::getInstance()->getConfig()->getRegion(),
                'version' => 'latest',
                'http_handler' => new GuzzleHandler(Craft::createGuzzleClient()),
                'credentials' => $this->createCredentials(),
            ],
            Module::getInstance()->getConfig()->s3ClientOptions,
            $config
        );

        return new S3Client($config);
    }

    public function getClient(): S3Client
    {
        if (!isset($this->client)) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    protected function visibility(): string
    {
        return Visibility::PRIVATE;
    }

    public function uploadDirectory(string $path, string $destPath, $config = [])
    {
        try {
            $config = $this->addFileMetadataToConfig($config);

            return $this->getClient()->uploadDirectory(
                $path,
                $this->getBucketName(),
                $this->prefixPath($destPath),
                $config,
            );
        } catch (Throwable $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    public function presignedUrl(string $command, string $path, DateTimeInterface $expiresAt, array $config = []): string
    {
        if (!Helper::isCraftCloud()) {
            throw new BadRequestHttpException('');
        }

        try {
            $commandConfig = $this->addFileMetadataToConfig($config);

            $command = $this->client->getCommand($command, [
                    'Bucket' => $this->getBucketName(),
                    'Key' => $this->prefixPath($path),
                ] + $commandConfig);

            $request = $this->client->createPresignedRequest(
                $command,
                $expiresAt,
            );

            return (string)$request->getUri();
        } catch (Throwable $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * Duping parent to add config…
     * @see https://github.com/craftcms/flysystem/pull/9
     */
    public function copyFile(string $path, string $newPath): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->copyFile($path, $newPath);
            return;
        }

        try {
            $config = $this->addFileMetadataToConfig([]);
            $this->filesystem()->copy($path, $newPath, $config);
        } catch (FilesystemException|UnableToCopyFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * Duping parent method to add config…
     * @see https://github.com/craftcms/flysystem/pull/9
     */
    public function renameFile(string $path, string $newPath): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->renameFile($path, $newPath);
            return;
        }

        try {
            $config = $this->addFileMetadataToConfig([]);
            $this->filesystem()->move($path, $newPath, $config);
        } catch (FilesystemException|UnableToMoveFile $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritdoc
     * Duping parent method to add config…
     * @see https://github.com/craftcms/flysystem/pull/9
     */
    public function createDirectory(string $path, array $config = []): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->createDirectory($path, $config);
            return;
        }

        try {
            $config = $this->addFileMetadataToConfig([]);
            $this->filesystem()->createDirectory($path, $config);
        } catch (FilesystemException|UnableToCreateDirectory $exception) {
            throw new FsException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileList(string $directory = '', bool $recursive = true): Generator
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->getFileList($directory, $recursive);
        }

        return parent::getFileList($directory, $recursive);
    }

    /**
     * @inheritDoc
     */
    public function getFileSize(string $uri): int
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->getFileSize($uri);
        }

        return parent::getFileSize($uri);
    }

    /**
     * @inheritDoc
     */
    public function getDateModified(string $uri): int
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->getDateModified($uri);
        }

        return parent::getDateModified($uri);
    }


    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, array $config = []): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->write($path, $contents, $config);
            return;
        }

        parent::write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->read($path);
        }

        return parent::read($path);
    }

    /**
     * @inheritDoc
     */
    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->writeFileFromStream($path, $stream, $config);
            return;
        }

        parent::writeFileFromStream($path, $stream, $config);
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->fileExists($path);
        }

        return parent::fileExists($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteFile(string $path): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->deleteFile($path);
            return;
        }

        parent::deleteFile($path);
    }

    /**
     * @inheritDoc
     */
    public function getFileStream(string $uriPath)
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->getFileStream($uriPath);
        }

        return parent::getFileStream($uriPath);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        if (!Helper::isCraftCloud()) {
            return $this->localFs->directoryExists($path);
        }

        return parent::directoryExists($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        if (!Helper::isCraftCloud()) {
            $this->localFs->deleteDirectory($path);
            return;
        }

        parent::deleteDirectory($path);
    }
}
