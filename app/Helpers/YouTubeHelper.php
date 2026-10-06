<?php

namespace App\Helpers;

class YouTubeHelper
{
    /**
     * Convert YouTube URL to embed URL
     * 
     * @param string $url
     * @return string|null
     */
    public static function getEmbedUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        // If already an embed URL, return as is
        if (strpos($url, 'youtube.com/embed') !== false) {
            return $url;
        }

        // Extract video ID from various YouTube URL formats
        $videoId = self::extractVideoId($url);
        
        if ($videoId) {
            return "https://www.youtube.com/embed/{$videoId}";
        }

        return null;
    }

    /**
     * Extract video ID from YouTube URL
     * 
     * @param string $url
     * @return string|null
     */
    private static function extractVideoId($url)
    {
        // Handle youtu.be format: https://youtu.be/GZEBn0N8RZg
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Handle youtube.com/watch format: https://www.youtube.com/watch?v=GZEBn0N8RZg
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Handle youtube.com/embed format: https://www.youtube.com/embed/GZEBn0N8RZg
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Handle youtube.com/v format: https://www.youtube.com/v/GZEBn0N8RZg
        if (preg_match('/youtube\.com\/v\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get thumbnail URL for YouTube video
     * 
     * @param string $url
     * @param string $quality (default, mqdefault, hqdefault, sddefault, maxresdefault)
     * @return string|null
     */
    public static function getThumbnailUrl($url, $quality = 'hqdefault')
    {
        $videoId = self::extractVideoId($url);
        
        if ($videoId) {
            return "https://img.youtube.com/vi/{$videoId}/{$quality}.jpg";
        }

        return null;
    }

    /**
     * Check if URL is a valid YouTube URL
     * 
     * @param string $url
     * @return bool
     */
    public static function isValidYouTubeUrl($url)
    {
        return self::extractVideoId($url) !== null;
    }
}
