@extends('layouts.admin')
@section('title','Thêm nội dung hướng dẫn - ' . $function->name)
@section('crumb','Cài đặt / Hướng dẫn / ' . $category->name . ' / ' . $function->name . ' / Thêm mới')

@section('content')
<article class="card">
    <form method="POST" action="{{ route('admin.settings.guides.functions.contents.store', [$category->id, $function->id]) }}">
        @csrf
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-12">
                <label class="block text-xs text-slate-500 mb-1">Tiêu đề <span class="text-rose-600">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" 
                       class="w-full rounded-lg border px-3 py-2" 
                       placeholder="Nhập tiêu đề hướng dẫn" required>
                @error('title')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-12 mt-4">
            <div class="md:col-span-12">
                <label class="block text-xs text-slate-500 mb-1">Link video (YouTube)</label>
                <input type="url" name="video_url" value="{{ old('video_url') }}" 
                       class="w-full rounded-lg border px-3 py-2" 
                       placeholder="https://youtu.be/GZEBn0N8RZg hoặc https://www.youtube.com/watch?v=GZEBn0N8RZg">
                <p class="text-xs text-slate-500 mt-1">Hỗ trợ các định dạng: youtu.be, youtube.com/watch, youtube.com/embed</p>
                @error('video_url')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-12 mt-4">
            <div class="md:col-span-12">
                <label class="block text-xs text-slate-500 mb-1">Nội dung hướng dẫn <span class="text-rose-600">*</span></label>
                <textarea name="content" rows="10" 
                          class="w-full rounded-lg border px-3 py-2" 
                          placeholder="Nhập nội dung hướng dẫn chi tiết">{{ old('content') }}</textarea>
                @error('content')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <button class="btn-primary">Lưu</button>
            <a href="{{ route('admin.settings.guides.functions.contents.index', [$category->id, $function->id]) }}" class="btn-soft">Hủy</a>
        </div>
    </form>
</article>

@push('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    let guideContentEditor;
    const contentTextarea = document.querySelector('textarea[name="content"]');
    const formEl = document.querySelector('form');

    ClassicEditor
        .create(contentTextarea, {
            simpleUpload: {
                uploadUrl: '{{ route("admin.settings.image-upload") }}',
                withCredentials: true,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            },
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'link', '|',
                    'bulletedList', 'numberedList', '|',
                    'outdent', 'indent', '|',
                    'imageUpload', 'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ]
            },
            image: {
                toolbar: [
                    'imageTextAlternative', 'imageStyle:full', 'imageStyle:side'
                ]
            },
            table: {
                contentToolbar: [
                    'tableColumn', 'tableRow', 'mergeTableCells'
                ]
            }
        })
        .then(editor => {
            guideContentEditor = editor;
            // Thêm SimpleUploadAdapter
            editor.plugins.get('FileRepository').createUploadAdapter = function(loader) {
                return new UploadAdapter(loader, '{{ route("admin.settings.image-upload") }}');
            };
            
            // Debug: Log khi editor ready
            console.log('CKEditor ready with upload adapter');
        })
        .catch(error => {
            console.error(error);
        });

    // Đồng bộ dữ liệu và tự kiểm tra validate trước khi submit
    if (formEl) {
        formEl.addEventListener('submit', function(e) {
            if (!guideContentEditor) return;
            const data = guideContentEditor.getData();
            // Gán lại dữ liệu cho textarea để server nhận được
            contentTextarea.value = data;
            // Loại bỏ tag để kiểm tra rỗng về mặt nội dung
            const textOnly = data.replace(/<[^>]*>/g, '').trim();
            if (!textOnly) {
                e.preventDefault();
                // Thông báo nhanh và focus vào editor (tránh focus vào textarea bị ẩn)
                alert('Vui lòng nhập nội dung hướng dẫn.');
                guideContentEditor.editing.view.focus();
            }
        });
    }

    // UploadAdapter class
    class UploadAdapter {
        constructor(loader, url) {
            this.loader = loader;
            this.url = url;
        }

        upload() {
            return this.loader.file
                .then(file => {
                    console.log('Starting upload for file:', file.name);
                    return new Promise((resolve, reject) => {
                        this._initRequest();
                        this._initListeners(resolve, reject, file);
                        this._sendRequest(file);
                    });
                });
        }

        abort() {
            if (this.xhr) {
                this.xhr.abort();
            }
        }

        _initRequest() {
            const xhr = this.xhr = new XMLHttpRequest();
            xhr.open('POST', this.url, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.responseType = 'json';
        }

        _initListeners(resolve, reject, file) {
            const xhr = this.xhr;
            const loader = this.loader;
            const genericErrorText = `Couldn't upload file: ${file.name}.`;

            xhr.addEventListener('error', () => reject(genericErrorText));
            xhr.addEventListener('abort', () => reject());
            xhr.addEventListener('load', () => {
                const response = xhr.response;
                
                console.log('Upload response:', response); // Debug log
                console.log('Response status:', xhr.status);

                if (xhr.status !== 200) {
                    return reject(`HTTP ${xhr.status}: ${genericErrorText}`);
                }

                if (!response || response.uploaded === false) {
                    return reject(response && response.error && response.error.message ? response.error.message : genericErrorText);
                }

                console.log('Upload successful, URL:', response.url);

                // Trả về URL để CKEditor có thể hiển thị ảnh
                resolve({
                    default: response.url
                });
            });

            if (xhr.upload) {
                xhr.upload.addEventListener('progress', evt => {
                    if (evt.lengthComputable) {
                        loader.uploadTotal = evt.total;
                        loader.uploaded = evt.loaded;
                    }
                });
            }
        }

        _sendRequest(file) {
            const data = new FormData();
            data.append('upload', file);
            this.xhr.send(data);
        }
    }
</script>
@endpush
@endsection