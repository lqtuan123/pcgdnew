# Ảnh mẫu cho Seeder

Thư mục này chứa các ảnh mẫu được sử dụng trong seeder để tạo nội dung hướng dẫn có ảnh.

## Cách sử dụng:

1. **Đặt ảnh vào thư mục này** với tên file rõ ràng
2. **Trong seeder**, sử dụng đường dẫn: `/images/seeder-images/tên-file.jpg`
3. **Ảnh sẽ hiển thị** trong nội dung hướng dẫn

## Ví dụ:

```php
'content' => '<p>Hướng dẫn bước 1:</p>
<img src="/images/seeder-images/step1.jpg" alt="Bước 1" style="max-width: 100%; height: auto; margin: 10px 0;">
<p>Tiếp tục...</p>'
```

## Lưu ý:

- Đặt tên file rõ ràng, dễ hiểu
- Sử dụng định dạng: jpg, png, gif, webp
- Kích thước ảnh nên tối ưu (không quá lớn)
- Alt text mô tả nội dung ảnh
