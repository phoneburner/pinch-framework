<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Storage\FilesystemAdapterFactory;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use PhoneBurner\Pinch\Component\Configuration\ConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\Config\S3FilesystemConfigStruct;
use PhoneBurner\Pinch\Framework\Storage\FilesystemAdapterFactory;

class S3FilesystemAdapterFactory implements FilesystemAdapterFactory
{
    public function make(ConfigStruct $config): FilesystemAdapter
    {
        \assert($config instanceof S3FilesystemConfigStruct);

        return new AwsS3V3Adapter(
            client: new S3Client($config->client),
            bucket: $config->bucket_name,
            prefix: $config->prefix,
            visibility: $config->visibility_converter,
            mimeTypeDetector: $config->mime_type_detector,
            options: $config->options,
            streamReads: $config->stream_reads,
            forwardedOptions: $config->forwarded_options ?? AwsS3V3Adapter::AVAILABLE_OPTIONS,
            multipartUploadOptions: $config->multipart_upload_options ?? AwsS3V3Adapter::MUP_AVAILABLE_OPTIONS,
        );
    }
}
