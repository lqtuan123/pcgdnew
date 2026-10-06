<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\GuideCategory;
use App\Models\GuideFunction;
use App\Models\GuideContent;

class GuideSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Xóa dữ liệu cũ trước khi tạo mới (theo thứ tự để tránh foreign key constraint)
        GuideContent::query()->delete();
        GuideFunction::query()->delete();
        GuideCategory::query()->delete();
        
        // Reset auto increment
        \DB::statement('ALTER TABLE guide_contents AUTO_INCREMENT = 1');
        \DB::statement('ALTER TABLE guide_functions AUTO_INCREMENT = 1');
        \DB::statement('ALTER TABLE guide_categories AUTO_INCREMENT = 1');
        
        // Tạo các danh mục
        $categories = [
            ['role_type' => 'user', 'name' => 'Danh mục'],
            ['role_type' => 'user', 'name' => 'Điều tra'],
            ['role_type' => 'user', 'name' => 'Thống kê'],
            ['role_type' => 'user', 'name' => 'Báo cáo'],
        ];

        foreach ($categories as $categoryData) {
            $category = GuideCategory::create($categoryData);

            // Tạo các chức năng cho từng danh mục
            $functions = $this->getFunctionsForCategory($category->name);
            
            foreach ($functions as $functionData) {
                $function = GuideFunction::create([
                    'category_id' => $category->id,
                    'name' => $functionData['name']
                ]);

                // Tạo nội dung hướng dẫn cho từng chức năng
                $contents = $this->getContentsForFunction($functionData['name']);
                
                foreach ($contents as $contentData) {
                    GuideContent::create([
                        'function_id' => $function->id,
                        'title' => $contentData['title'],
                        'content' => $contentData['content'],
                        'video_url' => $contentData['video_url'] ?? null
                    ]);
                }
            }
        }
    }

    private function getFunctionsForCategory($categoryName)
    {
        switch ($categoryName) {
            case 'Danh mục':
                return [
                    ['name' => 'Người dùng'],
                    ['name' => 'Đơn vị hành chính'],
                    ['name' => 'Danh mục trường'],
                    ['name' => 'Dân tộc'],
                    ['name' => 'Tôn giáo'],
                    ['name' => 'Văn bản'],
                    ['name' => 'Cấu hình'],
                ];
            case 'Điều tra':
                return [
                    ['name' => 'Danh sách phiếu điều tra'],
                    ['name' => 'Danh sách đối tượng điều tra'],
                    ['name' => 'Danh sách xóa tạm'],
                    ['name' => 'Học sinh mầm non'],
                    ['name' => 'Học sinh trái tuyến'],
                    ['name' => 'Kiểm tra dữ liệu'],
                    ['name' => 'Thực hiện lên lớp'],
                ];
            case 'Thống kê':
                return [
                    ['name' => 'Tra cứu thống kê'],
                    ['name' => 'Quản lý báo cáo'],
                    ['name' => 'So dò thống kê'],
                    ['name' => 'Kiểm tra thống kê'],
                    ['name' => 'Tình hình tài chính Mầm non'],
                ];
            case 'Báo cáo':
                return [
                    ['name' => 'Báo cáo kết quả, biên bản kiểm tra PCGD-XMC'],
                ];
            default:
                return [];
        }
    }

    private function getContentsForFunction($functionName)
    {
        $contents = [
            'Người dùng' => [
                [
                    'title' => 'Người dùng',
                    'content' => '<p>Cho phép người dùng thêm tài khoản mail để truy cập vào hệ thống và phân quyền người dùng. Có 2 loại tài khoản chính: quản trị và người dùng</p><p><strong>Bước 1:</strong> Vào Danh mục → Người dùng</p><figure class="image"><img src="/images/seeder-images/1758991884_68d8160c7bc80.png"></figure><p><strong>Bước 2:</strong> Bấm nút <strong>Tạo người dùng mới.</strong></p><figure class="image"><img src="/images/seeder-images/1758991891_68d8161308534.png"></figure><p><strong>Bước 3:</strong> Nhập các thông tin như email, họ tên, số điện thoại,…</p><p><strong>Bước 4:</strong> Phân quyền tài khoản</p><p>Loại tài khoản:</p><p>-&nbsp;Quản trị viên</p><p>- Người dùng</p><figure class="image"><img src="/images/seeder-images/1760663383_68f19757c4b81.png"></figure><p><strong>Bước 5: </strong>Phân quyền địa giới.</p><p><strong>Bước 6: </strong>Chọn vai trò có thể cấp:</p><p>Lựa chọn vai trò mà tài khoản mới này có thể cấp khi muốn tạo thêm tài khoản khác. Lưu ý vai trò có thể cấp sẽ ngang bằng hoặc thấp hơn của tài khoản hiện tại.</p><p><strong>Bước 7: </strong>Phân quyền chức năng.</p><figure class="image"><img src="/images/seeder-images/1758991946_68d8164a97afc.png"></figure><p><strong>Bước 8:</strong> Bấm <strong>Tạo người dùng</strong>.</p><figure class="image"><img src="/images/seeder-images/1758991954_68d816525905d.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Đơn vị hành chính' => [
                [
                    'title' => 'Đơn vị hành chính',
                    'content' => '<p>Xem các đơn vị hành chính trong cả nước bao gồm Tỉnh thành, Phường Xã, Thôn xóm</p><p>Vào Danh mục → Đơn vị hành chính</p><figure class="image"><img src="/images/seeder-images/1758992049_68d816b1b42f2.png"></figure><p>- Ở giao diện này, Thầy/Cô có thể chọn Tỉnh/Thành, Phường/Xã, Thôn/Tổ để xem danh sách.</p><p>- Trường hợp muốn thêm, xóa, sửa đơn vị hành chính, Thầy/Cô vui lòng liên hệ công ty để được hỗ trợ.</p><p>&nbsp;</p>',
                    'video_url' => null
                ],
            ],
            'Danh mục trường' => [
                [
                    'title' => 'Danh mục trường',
                    'content' => '<p>Xem và lấy mã trường dựa vào danh sách các trường trong cả nước bao gồm Mã trường, Tên trường, Bậc học,…</p><p>Vào Danh mục → Danh mục trường</p><figure class="image"><img src="/images/seeder-images/1758992080_68d816d0dc387.png"></figure><p>Phần mềm sẽ hiển thị danh sách thông tin của các trường, Thầy/Cô có lọc theo các tiêu chí.</p><p>Thầy/Cô có thể xuất excel danh mục trường phục vụ mục đích khác bằng cách bấm vào nút <strong>Xuất Excel.</strong></p><figure class="image"><img src="/images/seeder-images/1758992089_68d816d9ac52d.png"></figure><p>Riêng đối với tài khoản cấp trường (Mầm non, Tiểu học, THCS) sẽ có nút <strong>Thêm thông tin trường</strong>. Thầy/Cô có thể vào để nhập dữ liệu cơ sở vật chất hoặc giáo viên cho trường.</p><figure class="image"><img src="/images/seeder-images/1760664211_68f19a9359cc7.png"></figure><p>Để nhập dữ liệu, thầy cô bấm vào <strong>Nhập thông tin giáo viên</strong> hoặc <strong>Nhập thông tin cơ sở vật chất</strong>. Sau đó chọn năm học và bấm <strong>Lọc</strong>.</p><figure class="image"><img src="/images/seeder-images/1760664216_68f19a9811de1.png"></figure><p>Thầy/Cô có thể nhập trực tiếp bằng cách bấm vào <strong>Chỉnh sửa dữ liệu</strong>, chỉnh sửa sau đó bấm <strong>Lưu dữ liệu</strong>. Hoặc bấm <strong>Xuất Excel</strong>, chỉnh sửa file sau đó bấm <strong>Nhập Excel</strong>, chọn năm nhập, file vừa chỉnh sửa và bấm <strong>Nhập dữ liệu.</strong></p>',
                    'video_url' => null
                ],
            ],
            'Dân tộc' => [
                [
                    'title' => 'Dân tộc',
                    'content' => '<p>Xem danh sách các dân tộc hiện tại.</p><p>Vào Danh mục → Dân tộc.</p><figure class="image"><img src="/images/seeder-images/1758992114_68d816f2c47f1.png"></figure><p>Phần mềm sẽ hiển thị danh sách thông tin của các dân tộc, Thầy/Cô có lọc theo các tiêu chí.</p><p>&nbsp;</p>',
                    'video_url' => null
                ],
            ],
            'Tôn giáo' => [
                [
                    'title' => 'Tôn giáo',
                    'content' => '<p>Xem danh sách các tôn giáo hiện tại.</p><p>Vào Danh mục → Tôn giáo.</p><figure class="image"><img src="/images/seeder-images/1758992165_68d817258de5f.png"></figure><p>Phần mềm sẽ hiển thị danh sách thông tin của các tôn giáo, Thầy/Cô có lọc theo các tiêu chí.</p><p>Thầy/Cô có thể xuất excel các dân tộc phục vụ mục đích khác bằng cách bấm vào nút <strong>Xuất Excel.</strong></p><figure class="image"><img src="/images/seeder-images/1758992177_68d817318cbc7.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Văn bản' => [
                [
                    'title' => 'Thư viện văn bản',
                    'content' => '<p>Lưu trữ các văn bản thông tư, công văn, nghị định về công tác PCGD – XMC của Bộ Giáo dục và Đào tạo hoặc của Tỉnh tạo ban hành để Thầy/Cô tham khảo.</p><p>Vào Danh mục → Văn bản.</p><figure class="image"><img src="/images/seeder-images/1758992224_68d81760075cf.png"></figure><p>Thầy/Cô bấm nút <strong>Xem</strong> để tải văn bản về máy để xem.</p><p>&nbsp;</p>',
                    'video_url' => null
                ],
            ],
            'Cấu hình' => [
                [
                    'title' => 'Cấu hình',
                    'content' => '<p>Tùy chỉnh theo nhu cầu đơn vị phần liên quan đến báo cáo thống kê như: Phân tích số thập phân, công thức thống kê…</p><p>1.CẤU HÌNH THẬP PHÂN</p><p>Tùy chỉnh cách hiển thị phân tích số ở các số liệu thống kê trên phần mềm theo nhu cầu của đơn vị.</p><p>Vào Danh mục → Cấu hình → Phân tích thập phân.</p><figure class="image"><img src="/images/seeder-images/1761118115_68f887a3c413a.png"></figure><p>Tất cả số liệu ở thống kê trên phần mềm sẽ theo cấu hình đang chọn.</p><p>Mặc định sẽ check sẵn <strong>Cấu hình Việt Nam</strong>, Thầy/Cô có thể check chọn theo nhu cầu mong muốn của đơn vị.</p><figure class="image"><img src="/images/seeder-images/1758992340_68d817d458557.png"></figure><p>2.CHỈ TIÊU PCGD</p><p>Xem các chỉ tiêu PCGD của các cấp</p><p>Vào Danh mục → Cấu hình → Chỉ tiêu PCGD</p><figure class="image"><img src="/images/seeder-images/1761118115_68f887a3c413a.png"></figure><p>Thầy/Cô có thể chọn chương trình giáo dục và xem các chỉ tiêu tương ứng.</p><figure class="image"><img src="/images/seeder-images/1760664703_68f19c7f1a0c1.png"></figure><p>3.CẤU HÌNH BÁO CÁO</p><p>Xem các loại báo cáo thống kê nào cần gửi hay không cần gửi lên cấp trên.</p><p>Vào Danh mục → Cấu hình → Báo cáo.</p><figure class="image"><img src="/images/seeder-images/1761118115_68f887a3c413a.png"></figure><p><strong>Cột "Bắt buộc":</strong> Cần gửi lên cấp trên khi hoàn thành xong số liệu để tổng hợp số liệu cho tỉnh.</p><p><strong>Cột "Không bắt buộc":</strong> Không cần gửi lên cấp trên. Những báo cáo loại này thường là danh sách, mặc định cấp trên sẽ thấy không cần phải gửi.</p><figure class="image"><img src="/images/seeder-images/1758992355_68d817e32a3a8.png""></figure><p>&nbsp;</p><p>&nbsp;</p>',
                    'video_url' => null
                ],
            ],
            'Danh sách phiếu điều tra' => [
                [
                    'title' => 'Danh sách phiếu điều tra',
                    'content' => '<p>Cập nhật thông tin phiếu điều tra, tải và in phiếu điều tra.</p><p>Vào Điều tra → Danh sách Phiếu điều tra.</p><figure class="image"><img src="/images/seeder-images/1758992412_68d8181cdbe81.png"></figure><p>Chọn khu vực, năm học sau đó bấm <strong>Lọc.</strong></p><p>Lúc này phần mềm sẽ hiện danh sách Phiếu điều tra của thôn/tổ/xóm đã chọn.</p><figure class="image"><img src="/images/seeder-images/1758992420_68d81824cd076.png"></figure><p>1.TÌM KIẾM NHANH CHỦ HỘ.</p><p>Nhập số phiếu hoặc tên của chủ hộ và bấm <strong>Lọc.</strong> Khi này phần mềm sẽ hiện ra danh sách của hộ đó nếu có.</p><figure class="image"><img src="/images/seeder-images/1758992472_68d8185815549.png"></figure><p>2.THÊM MỚI PHIẾU ĐIỀU TRA</p><p>Có 2 cách để thêm mới phiếu điều tra.</p><p>Cách 1: Thêm trực tiếp.</p><p>Bấm vào <strong>Thêm phiếu.</strong></p><p>Nhập các thông tin của hộ rồi bấm nút <strong>Lưu</strong>.</p><p>Cách 2: Thêm từ Excel.</p><p>Bấm nút <strong>Nhập Excel.</strong></p><p>Sau đó thầy cô chọn năm, khu vực và chọn file excel đã thêm dữ liệu.</p><p>Sau khi chọn file, thầy cô có thể kiểm tra dữ liệu của file trong danh sách hiển thị và bấm <strong>Lưu vào hệ thống</strong>.</p><figure class="image"><img src="/images/seeder-images/1758992502_68d818762fb63.png"></figure><p>Nếu có lỗi dữ liệu nào được tìm thấy, phần mềm sẽ hiển thị ra trên giao diện và thầy cô có thể bấm vào <strong>Tải danh sách lỗi</strong> để xem các dòng bị sai thông tin để chỉnh sửa lại sau đó thêm lại các đối tượng này.</p><p>&nbsp;</p><p>3.XUẤT PHIẾU ĐIỀU TRA</p><p><strong>Bước 1:</strong> Chọn phiếu điều tra cần in. Có thể chọn 1 hoặc nhiều phiếu cùng lúc bằng cách check vào ô vuông trước PĐT hoặc bấm nút <strong>Chọn tất cả.</strong></p><figure class="image"><img src="/images/seeder-images/1758992532_68d8189430135.png"></figure><p><strong>Bước 2</strong>: Bấm nút <strong>Xuất Excel</strong> ()</p><p>File Excel xuất về sẽ có giao diện của phiếu điều tra, mỗi trang (sheet) sẽ là một phiếu. Tối đa xuất 1 lần là 30 phiếu.</p><p>&nbsp;</p><p>4.CHỈNH SỬA PHIẾU ĐIỀU TRA</p><p>Bấm vào nút 3 chấm ở đầu phiếu, chọn <strong>Điều chỉnh phiếu.</strong></p><figure class="image"><img src="/images/seeder-images/1758992552_68d818a8c237c.png"></figure><p>Sau khi chỉnh sửa thông tin phiếu thì bấm nút <strong>Cập nhật.</strong></p><p>&nbsp;</p><p>5.TÁCH HỘ</p><p>Bấm vào nút 3 chấm ở đầu phiếu, chọn <strong>Tách hộ.</strong></p><figure class="image"><img src="/images/seeder-images/1758992563_68d818b3bd627.png"></figure><p>Chọn số hộ mới muốn tách, sau đó chọn thành viên nào sẽ chuyển sang hộ mới.</p><figure class="image"><img src="/images/seeder-images/1758992625_68d818f1073d7.png"></figure><p>Ở thông tin hộ mới Thầy/Cô nhập Số phiếu (không được trùng), chọn chủ hộ và thiết lập các mối quan hệ giữa thành viên trong hộ mới. Sau đó bấm <strong>Tách hộ và Lưu.</strong></p><figure class="image"><img src="/images/seeder-images/1758992721_68d819515d252.png"></figure><p>6.GHÉP HỘ</p><p>Bấm vào nút 3 chấm ở đầu phiếu, chọn <strong>Ghép hộ.</strong></p><figure class="image"><img src="/images/seeder-images/1758992733_68d8195d66475.png"></figure><p>Tìm hộ muốn ghép vào bằng cách tra Số phiếu hoặc Tên của chủ hộ.</p><figure class="image"><img src="/images/seeder-images/1758992741_68d81965b89d1.png"></figure><p>Chọn người làm Chủ hộ sau khi ghép và thiết lập các mối quan hệ giữa các thành viên trong hộ rồi bấm <strong>Ghép hộ và Lưu.</strong></p><figure class="image"><img src="/images/seeder-images/1758992794_68d8199a214af.png"></figure><p>7.CHUYỂN HỘ</p><p>Bấm vào nút 3 chấm ở đầu phiếu, chọn <strong>Chuyển hộ.</strong></p><figure class="image"><img src="/images/seeder-images/1758992803_68d819a3c630c.png"></figure><p>Nhập số phiếu mới (không được trùng) cho hộ, chọn khu vực mà hộ sẽ chuyển tới sau đó bấm <strong>Chuyển hộ và Lưu.</strong></p><figure class="image"><img src="/images/seeder-images/1758992814_68d819ae86d7.png"></figure><p>8.XÓA PHIẾU ĐIỀU TRA</p><p><strong>Cách 1:</strong> Bấm vào nút 3 chấm ở đầu phiếu, chọn <strong>Xóa phiếu.</strong></p><figure class="image"><img src="/images/seeder-images/1758992825_68d819b91650b.png"></figure><p><strong>Cách 2:</strong> Tích chọn các phiếu cần xóa và bấm nút <strong>Xóa các phiếu đã chọn ().</strong></p><figure class="image"><img src="/images/seeder-images/1758992831_68d819bf395a3.png"></figure><p><strong>Lưu ý:</strong></p><p>Nút <strong>Xóa hết (theo bộ lọc)&nbsp;</strong>sẽ xóa tất cả phiếu đang lọc hiện tại.</p><p>Nút <strong>Xóa toàn bộ</strong> sẽ xóa tất cả phiếu trong khu vực mà tài khoản quản lý.</p><p>Các phiếu bị xóa sẽ được đưa vào danh sách xóa tạm.</p><p>&nbsp;</p>',
                    'video_url' => null
                ],
            ],
            'Danh sách đối tượng điều tra' => [
                [
                    'title' => 'Danh sách đối tượng điều tra',
                    'content' => '<p>Hiển thị danh sách tất cả đối tượng điều tra bao gồm chủ hộ và các đối tượng trong hộ đó. Trong danh sách này Thầy/Cô có thể lọc tìm, xóa chỉnh sửa thông tin của đối tượng một cách nhanh chóng</p><p>Vào Điều tra&nbsp;→ Danh sách đối tượng điều tra.</p><figure class="image"><img src="/images/seeder-images/1758993249_68d81b617124f.png"></figure><p>Chọn khu vực, năm học sau đó bấm <strong>Lọc.</strong></p><p>Lúc này phần mềm sẽ hiện danh sách Đối tượng điều tra của khu vực đã chọn.</p><figure class="image"><img src="/images/seeder-images/1760665298_68f19ed2e95ef.png"></figure><p>1.LỌC TÌM ĐỐI TƯỢNG</p><p>Nhập thông tin cần tìm như số phiếu, tên, tuổi hay dân tộc rồi bấm nút&nbsp;<strong>Lọc</strong>&nbsp;sẽ ra kết quả tìm kiếm.&nbsp;</p><p>2.CHỈNH SỬA THÔNG TIN ĐỐI TƯỢNG</p><p>Bấm vào nút 3 chấm ở đầu dòng của đối tượng và chọn <strong>Điều chỉnh.</strong></p><figure class="image"><img src="/images/seeder-images/1758993269_68d81b751aefa.png"></figure><p>Sau khi chỉnh sửa các thông tin của đối tượng thì bấm nút <strong>Cập nhật.</strong></p><p>3.XUẤT DANH SÁCH ĐỐI TƯỢNG ĐIỀU TRA</p><p>Sau khi lọc thông tin đối tượng, Thầy/Cô có thể bấm <strong>Xuất Excel</strong> để xuất danh sách ra và chỉnh sửa thông tin đối tượng trực tiếp ở file excel sau đó đưa vào phần mềm.</p><p>Để đưa file excel vào phần mềm, Thầy/Cô bấm <strong>Nhập Excel</strong> và chọn lại file excel đã chỉnh sửa.</p><p>4.XÓA TẠM ĐỐI TƯỢNG</p><p>Trường hợp Thầy/Cô muốn xóa đối tượng nào đó để không ảnh hưởng tới thống kê mà vẫn muốn sau này có thể khôi phục lại đối tượng đã xóa thì sử dụng tính năng xóa tạm.</p><p>Thầy/Cô check chọn vào ô vuông ở đầu dòng của đối tượng muốn xóa tạm sau đó bấm nút <strong>Xóa tạm ()</strong>.</p><figure class="image"><img src="/images/seeder-images/1760665352_68f19f08ba7b9.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Danh sách xóa tạm' => [
                [
                    'title' => 'Danh sách xóa tạm',
                    'content' => '<p>Lưu các đối tượng đã xóa tạm ở Danh sách phiếu điều tra hoặc Danh sách đối tượng điều tra. Ở danh sách này có thể khôi phục lại phiếu và đối tượng đã xóa tạm.</p><p>Vào Điều tra → Danh sách xóa tạm.</p><figure class="image"><img src="/images/seeder-images/1758993429_68d81c1576475.png"></figure><p>Chọn năm học, khu vực sau đó bấm <strong>Lọc.</strong></p><p>Phần mềm sẽ hiển thị các đối tượng hoặc phiếu điều tra đã xóa tạm dựa theo điều kiện lọc.</p><figure class="image"><img src="/images/seeder-images/1758993435_68d81c1b4cbe1.png"></figure><p>Để phục hồi đối tượng hoặc phiếu điều tra, Thầy/Cô tích chọn các đối tượng và bấm nút <strong>Phục hồi ()</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993441_68d81c214c1ca.png"></figure><p>Lưu ý chỉ những đối tượng điều tra có phiếu điều tra hiện đang hoạt động mới có thể phục hồi. Nếu phục hồi các đối tượng thuộc phiếu điều tra hiện đang bị xóa tạm thì phần mềm sẽ thông báo cần phục hồi phiếu điều đó để phục hồi được đối tượng.</p><p>Khi phục hồi 1 phiếu điều tra thì các đối tượng thuộc phiếu đó cũng sẽ được phục hồi.</p><p>Để xóa thật đối tượng hoặc phiếu điều tra, Thầy/Cô tích chọn các đối tượng/phiếu rồi bấm <strong>Xóa thật ()</strong>. Khi này phần mềm sẽ xóa hoàn toàn đối tượng/phiếu và không thể phục hồi lại nữa.</p>',
                    'video_url' => null
                ],
            ],
            'Học sinh mầm non' => [
                [
                    'title' => 'Danh sách học sinh mầm non',
                    'content' => '<p>&nbsp;</p><p>Bổ sung thông tin mầm non cho các đối tượng mầm non như: Học bán trú, học đủ ngày theo quy định, chuyên cần,…</p><p>Vào Điều tra → Học sinh mầm non.</p><figure class="image"><img src="/images/seeder-images/1758993543_68d81c87b12b1.png"></figure><p>1.LỌC TÌM ĐỐI TƯỢNG MẦM NON</p><p>Chọn năm học, khu vực và các thông tin cần lọc như tên hay độ tuổi và bấm <strong>Lọc dữ liệu.</strong></p><figure class="image"><img src="/images/seeder-images/1758993548_68d81c8cd8bce.png"></figure><p>2.CHỈNH SỬA THÔNG TIN ĐỐI TƯỢNG MẦM NON</p><p>Có 2 cách chỉnh sửa thông tin:</p><p><strong>Cách 1:</strong> Chỉnh sửa trực tiếp: Thầy/Cô bấm vào nút <strong>Sửa</strong> ở cuối dòng của học sinh đó. Sau đó chỉnh sửa các thông tin và bấm <strong>Cập nhật</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993554_68d81c9222a10.png"></figure><p><strong>Cách 2:</strong> Để chỉnh sửa hàng loạt Thầy/Cô lọc ra danh sách học sinh rồi chọn nút <strong>Xuất Excel</strong>, sau đó Thầy/Cô chỉnh sửa trên file Excel rồi vào chọn nút <strong>Nhập Excel</strong> để nhập lại file đã chỉnh sửa theo năm và khu vực tương ứng.</p><figure class="image"><img src="/images/seeder-images/1758993559_68d81c97666c0.png"></figure><p>3.XÓA ĐỐI TƯỢNG MẦM NON</p><p>Check chọn đối tượng muốn xóa sau đó bấm <strong>Xóa đã chọn ()</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993712_68d81d305b939.png"></figure><p>4.BỔ SUNG THÔNG TIN HỌC TẬP</p><p>Cập nhật tự động các thông tin học tập như: Học 2 buổi, Chuyên cần, Trẻ dân tộc được CBTV (đối với dân tộc khác "Kinh")</p><p><strong>Bước 1:</strong> Tích chọn các học sinh cần bổ sung thông tin học tập.</p><p><strong>Bước 2:</strong> Chọn nút <strong>Bổ sung thông tin học tập ()</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993718_68d81d36193c8.png"></figure><p>5.BỔ SUNG HOÀN THÀNH CT GDMN CHO TRẺ 5 TUỔI</p><p>Cập nhật tự động cột "Hoàn thành chương trình GDMN".</p><p><strong>Bước 1:</strong> Tích chọn các học sinh cần bổ hoàn thành CT GDMN.</p><p><strong>Bước 2:</strong> Chọn nút <strong>Bổ sung hoàn thành GDMN ()</strong>.</p>',
                    'video_url' => null
                ],
            ],
            'Học sinh trái tuyến' => [
                [
                    'title' => 'Danh sách học sinh trái tuyến',
                    'content' => '<p>Cập nhật danh sách học sinh có hộ khẩu ở nơi khác đến học ở các trường trên địa bàn Phường/Xã của Thầy/Cô.</p><p>Vào Điều tra → Học sinh trái tuyến.</p><figure class="image"><img src="/images/seeder-images/1758993961_68d81e29548d4.png"></figure><p>1.THÊM HỌC SINH TRÁI TUYẾN</p><p>Có 2 cách để thêm học sinh trái tuyến đến:</p><p><strong>Cách 1:</strong> Nhập từ Excel:</p><p><strong>Bước 1:</strong> Chọn nút <strong>Nhập Excel</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993967_68d81e2f8fec0.png"></figure><p><strong>Bước 2:&nbsp;</strong>Chọn loại file Excel:</p><p>Mặc định phần mềm sẽ chọn file Excel mới (file excel của phần mềm hiện đang sử dụng).</p><p><strong>LƯU Ý:&nbsp;</strong>Nếu là file dữ liệu <strong>hs trái tuyến có số định danh</strong> từ <strong>phần mềm cũ</strong> thì chọn file Excel cũ (chỉ dùng cho bước chuyển đổi phần mềm cũ sang mới).</p><p>Chọn năm học, khu vực, file danh sách học sinh trái tuyến và chọn <strong>Lưu vào hệ thống.</strong></p><figure class="image"><img src="/images/seeder-images/1758993975_68d81e3796ffe.png"></figure><p>Nếu có dữ liệu lỗi thì phần mềm sẽ hiển thị lên giao diện và thầy cô có thể bấm <strong>tải file Excel lỗi</strong> về để xem và sửa lại dữ liệu. Sau đó thêm lại các dữ liệu này vào sau.</p><p><strong>Cách 2:&nbsp;</strong>Nhập trực tiếp:</p><p>Bấm vào nút <strong>Thêm mới</strong>.</p><figure class="image"><img src="/images/seeder-images/1758993984_68d81e40dcd0b.png"></figure><p>Nhập các thông tin của học sinh mới và nhấn <strong>Thêm mới</strong> để xác nhận thêm học sinh.</p><figure class="image"><img src="/images/seeder-images/1758993988_68d81e44c419d.png"></figure><p>2.CHỈNH SỬA THÔNG TIN HỌC SINH TRÁI TUYẾN</p><p>Có 2 cách chỉnh sửa thông tin:</p><p><strong>Cách 1:&nbsp;</strong>Sửa trực tiếp:</p><p>Bấm vào nút <strong>Sửa</strong> ở đầu mỗi dòng.</p><figure class="image"><img src="/images/seeder-images/1758993995_68d81e4bccbdc.png"></figure><p>Chỉnh sửa các thông tin sau đó bấm <strong>Cập nhật.</strong></p><p><strong>Cách 2:&nbsp;</strong>Chỉnh sửa hàng loạt bằng Excel<br>Thầy/Cô bấm vào nút <strong>Xuất Excel</strong> để xuất ra danh sách các học sinh đã lọc.</p><figure class="image"><img src="/images/seeder-images/1758994004_68d81e54917ef.png"></figure><p>Cập nhật thông tin ở file Excel sau đó bấm nút <strong>Nhập Excel</strong> để nhập lại file này vào lại năm và khu vực đã lọc.</p><p>3.XÓA HỌC SINH TRÁI TUYẾN</p><p>Tích chọn các học sinh cần xóa sau đó bấm nút <strong>Xóa các học sinh đã chọn</strong></p><figure class="image"><img src="/images/seeder-images/1758994010_68d81e5aa41a8.png"></figure><p>Nút <strong>Xóa hết (theo bộ lọc)</strong> sẽ xóa tất cả học sinh trong danh sách lọc hiện tại.</p><p>4.XUẤT EXCEL DANH SÁCH ĐỐI TƯỢNG TRÁI TUYẾN ĐẾN</p><p>Thầy/Cô bấm vào nút <strong>Xuất Excel</strong> để xuất ra danh sách các học sinh đã lọc.</p><figure class="image"><img src="/images/seeder-images/1758994053_68d81e859b610.png"></figure><p>5.LÊN LỚP HỌC SINH TRÁI TUYẾN.</p><p>Chuyển đối tượng từ năm hiện tại qua năm tiếp theo và tự động tăng khối lớp 1 đơn vị cho các học sinh.</p><p>Vào Điều tra → Lên lớp học sinh trái tuyến.</p><figure class="image"><img src="/images/seeder-images/1758994060_68d81e8cad6d8.png"></figure><p>Chọn khu vực muốn lên lớp cho học sinh trái tuyến.</p><p>Chọn trường cho các em học sinh chuyển cấp sẽ đăng ký vào học nhiều nhất trong năm sau.</p><p>Nhấn nút <strong>Thực hiện lên lớp</strong>.</p><figure class="image"><img src="/images/seeder-images/1758994061_68d81e8d7d1c0.png"></figure><p>Sau khi lên lớp nhấn <strong>Khóa</strong> để tránh người khác vào và lên lớp lần 2.</p><p>Lưu ý chỉ có tài khoản khóa mới có thể mở khóa.</p><figure class="image"><img src="/images/seeder-images/1758994138_68d81edaa4763.png"></figure><figure class="image"><img src="/images/seeder-images/1758994142_68d81ede8232f.png"></figure><figure class="image"><img></figure>',
                    'video_url' => null
                ],
            ],
            'Kiểm tra dữ liệu' => [
                [
                    'title' => 'Kiểm tra dữ liệu',
                    'content' => '<p>Kiểm tra lại các đối tượng có thông tin không hợp lệ hoặc sai logic theo nhiều tiêu chí khác nhau.</p><p>Vào Điều tra → Kiểm tra dữ liệu</p><figure class="image"><img src="/images/seeder-images/1758994216_68d81f28724ea.png"></figure><p>Lựa chọn năm học, khu vực và tiêu chí muốn kiểm tra sau đó bấm <strong>Kiểm tra dữ liệu</strong>.</p><figure class="image"><img src="/images/seeder-images/1758994220_68d81f2c42844.png"></figure><p>Có thể bấm vào nút <strong>Mô tả</strong> nằm bên cạnh tiêu chí để xem mô tả của tiêu chí đó.</p><p>Thầy/Cô có thể chỉnh sửa hoặc xóa các đối tượng tại danh sách hiển thị sau khi nhấn kiểm tra dữ liệu.</p><figure class="image"><img src="/images/seeder-images/1758994225_68d81f3158808.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Thực hiện lên lớp' => [
                [
                    'title' => 'Thực hiện lên lớp',
                    'content' => '<p>Cập nhật dữ liệu điều tra năm trước vào năm hiện tại (lên lớp cho các đối tượng đang đi học, các đối tượng còn lại sẽ giữ nguyên thông tin và tự động chuyển dữ liệu của đối tượng đó lên năm học hiện tại)</p><p>Vào Điều tra → Thực hiện lên lớp.</p><figure class="image"><img src="/images/seeder-images/1758994700_68d8210c049f3.png"></figure><p>Thầy/Cô chọn năm học, khu vực để lên lớp.&nbsp;</p><figure class="image"><img src="/images/seeder-images/1758994709_68d821150a492.png"></figure><p>Chọn trường cho các em học sinh chuyển cấp sẽ đăng ký vào học nhiều nhất trong năm sau rồi bấm <strong>Thực hiện lên lớp và lên cấp</strong></p><figure class="image"><img src="/images/seeder-images/1758994715_68d8211b156c5.png"></figure><p>Sau khi lên lớp nhấn <strong>Khóa</strong> để tránh người khác vào và lên lớp lần 2.</p><p>Lưu ý chỉ có tài khoản khóa mới có thể mở khóa.</p><figure class="image"><img src="/images/seeder-images/1758994719_68d8211f1e192.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Tra cứu thống kê' => [
                [
                    'title' => 'Tra cứu thống kê',
                    'content' => '<p>Tổng hợp số liệu từ danh sách phiếu điều tra, danh sách trái tuyến vào các mẫu thống kê báo cáo theo quy định.</p><p>Vào Thống kê → Tra cứu thống kê</p><figure class="image"><img src="/images/seeder-images/1761119334_68f88c66dfdc0.png"></figure><p>Chọn năm học, loại hình, và chọn báo cáo sau đó bấm <strong>Xem báo cáo</strong>.</p><figure class="image"><img src="/images/seeder-images/1760667690_68f1a82a8fbf7.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Quản lý báo cáo' => [
                [
                    'title' => 'Quản lý báo cáo',
                    'content' => '<p>Tính năng này giúp Thầy/Cô theo dõi tiến độ, xem phản hồi từ cấp trên, gửi báo cáo.</p><p>Vào Thống kê → Quản lý báo cáo</p><figure class="image"><img src="/images/seeder-images/1761119364_68f88c848cf80.png"></figure><p>1.QUẢN LÝ HẠN NỘP</p><p>Tạo hạn nộp báo cáo cho các cấp dưới.</p><p>Chọn đơn vị nhận báo cáo, chọn nhóm báo cáo, loại báo cáo, năm học, hạn nộp sau đó nhấn Lưu hạn nộp.</p><figure class="image"><img src="/images/seeder-images/1760667809_68f1a8a15c49c.png"></figure><p>Có thể bấm sang Danh sách để hiển thị và chỉnh sửa các hạn nộp đã tạo.</p><figure class="image"><img src="/images/seeder-images/1760667815_68f1a8a79256b.png"></figure><p>2.TẢI LÊN BÁO CÁO</p><p>Tải lên báo cáo cho cấp trên theo yêu cầu.</p><p>Thầy cô chọn nhóm báo cáo, loại báo cáo, năm học, File báo cáo sau đó nhấn <strong>Tải báo cáo lên.</strong></p><figure class="image"><img src="/images/seeder-images/1760667821_68f1a8ad02f4b.png"></figure><p>Báo cáo sau khi tải lên sẽ hiển thị trong danh sách báo cáo đã gửi, ở đây thầy cô nhấn <strong>Chuyển tiếp</strong> để gửi lên cấp trên, hoặc chỉnh sửa/xóa.</p><figure class="image"><img src="/images/seeder-images/1760667828_68f1a8b4ba308.png"></figure><p>Sau khi chuyển tiếp lên cấp trên thầy cô sẽ không thể sửa báo cáo, thầy cô có thể xem trạng thái của báo cáo:</p><p>- Đã gửi và không hiển thi thao tác nghĩa là báo cáo đã gửi lên cấp trên, chờ được xử lý.</p><p>- Đã duyệt nghĩa là báo cáo đã được cấp trên duyệt.</p><p>- Trả lại nghĩa là cấp trên không chấp nhận và cần phải gửi lại, có thể xem phản hồi nếu có.</p><p>Riêng đối với các cấp Tỉnh/Thành phố hoặc Phường/Xã thì sẽ có chức năng duyệt báo cáo của cấp dưới gửi lên.</p><figure class="image"><img src="/images/seeder-images/1760667833_68f1a8b907d99.png"></figure>',
                    'video_url' => null
                ],
            ],
            'So dò thống kê' => [
                [
                    'title' => 'So dò thống kê',
                    'content' => '<p>Một tiêu chí phổ cập, có thể xuất hiện ở nhiều mẫu thống kê, phần mềm thu thập các số liệu tại các mẫu thống kê đó để Thầy/Cô dễ dàng so dò số liệu mà không cần kiểm tra, so sánh từng mẫu.</p><p>Vào Thống kê → So dò thống kê</p><figure class="image"><img src="/images/seeder-images/1761120043_68f88f2b8cc80.png"></figure><p>Tại đây Thầy/Cô có thể so dò các tiêu chí về Phổ cập giáo dục hoặc Xóa mù chữ giữa các bảng báo cáo. Thầy/Cô chọn khu vực, năm học và nhấn xem báo cáo.</p><figure class="image"><img src="/images/seeder-images/1760716928_68f2688041613.png"></figure><p>Thầy/Cô có thể nhấn <strong>Làm mới dữ liệu</strong> để phần mềm load lại dữ liệu mới nhất.</p>',
                    'video_url' => null
                ],
            ],
            'Kiểm tra thống kê' => [
                [
                    'title' => 'Kiểm tra thống kê',
                    'content' => '<p>Kiểm tra số liệu từ 6 đến 18 tuổi ở các thống kê có logic hay chưa, đảm bảo nhất quán số liệu giữa các thống kê</p><p>Vào Thống kê → Kiểm tra thống kê</p><figure class="image"><img src="/images/seeder-images/1761120116_68f88f74b078d.png"></figure><p>Phần mềm hiển thị số liệu từ 6 đến 18 tuổi kèm theo kết quả kiểm tra từng độ tuổi đã logic hay chưa.</p><p>Có 3 biểu tượng ở kết quả kiểm tra:</p><p>✓&nbsp;Đạt tiêu chí</p><p>◐&nbsp;Cần theo dõi</p><p>✗Chưa đạt</p><figure class="image"><img src="/images/seeder-images/1761120121_68f88f79c86ab.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Tình hình tài chính Mầm non' => [
                [
                    'title' => 'Tình hình tài chính Mầm non',
                    'content' => '<p>Nhập thông tin báo cáo tài chính chi cho giáo dục mầm non theo từng năm.</p><p>Vào Thống kê → Tình hình tài chính Mầm non</p><figure class="image"><img src="/images/seeder-images/1761120197_68f88fc5e0b52.png"></figure><p>Thầy/Cô có thể thêm dữ liệu cho từng tiêu chí bằng nút <strong>Thêm dữ liệu</strong> hoặc xuất excel để điền và nhập excel vào hệ thống.</p><figure class="image"><img src="/images/seeder-images/1761120202_68f88fca43a4a.png"></figure>',
                    'video_url' => null
                ],
            ],
            'Báo cáo kết quả, biên bản kiểm tra PCGD-XMC' => [
                [
                    'title' => 'Báo cáo kết quả, biên bản kiểm tra PCGD-XMC',
                    'content' => '<p>1.BÁO CÁO KẾT QUẢ PCGD</p><p>Thầy/Cô chỉ cần nhập các nội dung như ưu điểm, hạn chế, phương hướng phần mềm sẽ hỗ trợ tự động cập nhật số liệu ở các mẫu thống kê vào báo cáo kết quả PCGD</p><ul><li>Vào Báo cáo → Báo cáo kết quả PCGD</li></ul><figure class="image"><img src="/images/seeder-images/1760669303_68f1ae773a6e4.png"></figure><p>Chọn khu vực, năm học và nhập các thông tin như đánh giá chung và phương hướng sau đó bấm <strong>Xuất báo cáo</strong></p><figure class="image"><img src="/images/seeder-images/1760669308_68f1ae7cc619e.png"></figure><p>File báo cáo sẽ có định dạng Word, Thầy/Cô mở file ra sẽ có định dạng như hình.</p><figure class="image"><img src="/images/seeder-images/1760669313_68f1ae8188e0f.png"></figure><p>2.BIÊN BẢN KIỂM TRA KẾT QUẢ PCGD-XMC</p><p>Dựa vào số liệu thống kê các cấp, phần mềm hỗ trợ tự động xuất biên bản kết quả kiểm tra phổ cập theo mẫu.</p><p>Vào Báo cáo → Biên bản kiểm tra kết quả PCGD - XMC</p><p>Chọn năm học, nhập Nội dung tự kiểm tra, đề xuất kiến nghị sau đó bấm <strong>Xuất biên bản</strong>.</p><p>File xuất về cũng có định dạng là Word tương tự báo cáo PCGD.</p>',
                    'video_url' => null
                ],
            ],
        ];

        return $contents[$functionName] ?? [
            [
                'title' => 'Hướng dẫn cơ bản',
                'content' => '<h3>Hướng dẫn sử dụng chức năng ' . $functionName . '</h3><p>Nội dung hướng dẫn chi tiết sẽ được cập nhật sớm.</p>',
                'video_url' => null
            ]
        ];
    }
}