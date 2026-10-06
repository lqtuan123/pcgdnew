<?php

namespace App\Http\Controllers;

use App\Models\GuideCategory;
use App\Models\GuideFunction;
use App\Models\GuideContent;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    /**
     * Hiển thị trang chủ hướng dẫn
     */
    public function index()
    {
        $categories = GuideCategory::with('functions')->get();
        $selectedCategory = null;
        $selectedFunction = null;
        
        return view('front.guides.index', compact('categories', 'selectedCategory', 'selectedFunction'));
    }

    /**
     * Hiển thị danh sách chức năng theo category
     */
    public function category($categoryId)
    {
        $categories = GuideCategory::with('functions')->get();
        $selectedCategory = GuideCategory::with('functions')->findOrFail($categoryId);
        $functions = $selectedCategory->functions;
        
        return view('front.guides.category', compact('categories', 'selectedCategory', 'functions'));
    }

    /**
     * Hiển thị danh sách nội dung hướng dẫn theo function
     */
    public function function($functionId)
    {
        $categories = GuideCategory::with('functions')->get();
        $selectedFunction = GuideFunction::with(['category', 'contents'])->findOrFail($functionId);
        $selectedCategory = $selectedFunction->category;
        $contents = $selectedFunction->contents;
        
        return view('front.guides.function', compact('categories', 'selectedCategory', 'selectedFunction', 'contents'));
    }

    /**
     * Hiển thị chi tiết nội dung hướng dẫn (deprecated - redirect to function)
     */
    public function content($contentId)
    {
        $content = GuideContent::with(['function'])->findOrFail($contentId);
        return redirect()->route('front.guide.function', $content->function->id);
    }
}
