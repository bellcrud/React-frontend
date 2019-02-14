<?php

namespace App\Http\Controllers;

use App\Item;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as StatusCode;//追加
use Illuminate\Contracts\Validation\Validator;  // 追加
use Storage;
class ItemsController extends Controller
{
    public function errorValidation (Validator $validator)
    {
        $response['errors']  = $validator->errors()->toArray();

        abort_if($validator->fails(), StatusCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors(),$response['errors'] );

    }

    /**
     * アイテム全件取得
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $items = Item::itemAll();
        $itemsCount = $items->count();
        //アイテムが存在していない場合メッセージを返す。
        if($itemsCount === 0){
            //return response()->json(["message" => "アイテムが登録されていません"]);
            return response()->json(
                ["message" => "アイテムが登録されていません"],
                200,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }
        return response()->json(
            ["items" => $items, "count" => $itemsCount],
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );

    }


    /**
     * アイテム登録
     * @param Request $request
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'max:100|required',
            'description' => 'max:500|required',
            'price' => 'digits_between:1,11|required',
            'image' => 'required'
        ]);

        if ($validator->fails()){
            $this->errorValidation($validator);
        }

        $params = $request->all();

        //画像ファイルアップロードと返り値にファイル名取得
        $params['image'] = self::imageUpload($params['image']);

        //データ登録処理
        $item = Item::storeItem($params);
        return response()->json(
            ["item" => $item, "message" => "登録が完了しました。"],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }


    /**
     * アイテム取得
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        //idに格納されている値がintegerか確認
        $input = ['id' => $id];
        $rule = ['id' => 'integer'];
        $rule = ['id' => 'integer'];
        $validator = \Validator::make( $input, $rule );
        if($validator->fails()) {
            abort(404);
        }

        $item = Item::find($id);
        if($item) {
            //return response()->json(['item' => $item]);
            return response()->json(
                ['item' => $item],
                200,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }else{
            abort(404);
        }
    }


    /**
     * アイテム更新
     * @param Request $request
     * @param $id
     * @throws \Throwable
     */
    public function update(Request $request, $id)
    {
        //idのバリデーションチェック
        $input = ['id' => $id];
        $rule = ['id' => 'integer'];
        $validator = \Validator::make( $input, $rule );
        if($validator->fails()) {
            abort(421);
        }

        //対象データがあれば更新する
        $item = Item::find($id);
        if($item){
            $validator = \Validator::make($request->all(), [
                'name' => 'max:100',
                'description' => 'max:500',
                'price' => 'digits_between:1,11',
            ]);

            if ($validator->fails()){
                $this->errorValidation($validator);
            }

            $params = $request->all();
            $image = array_key_exists('image', $params);
            //imageプロパティが空でなければ、画像をストレージに保存

            if($image){

                //元画像削除
                self::imageDelete($item->getAttribute('image'));

                //画像登録
                $params['image'] = self::imageUpload($params['image']);
            }

            //データ更新処理
            $item = Item::updateItem($params,$id);
            $message = '更新が完了しました。';
            return response()->json(
                ['item' => $item, 'message' => $message],
                201,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }else{

                abort(404);
        }
    }


    /**
     * アイテム削除
     * @param $id
     * @return string
     * @throws \Throwable
     */
    public function destroy($id)
    {
        //idのバリデーション チェック
        $input = ['id' => $id];
        $rule = ['id' => 'integer'];
        $validator = \Validator::make( $input, $rule );
        if($validator->fails()) {
            abort(421);
        }

        //対象データの存在確認
        $item = Item::find($id);
        if($item) {
            //保存されている画像ファイルを削除
            self::imageDelete($item->getAttribute('image'));

            //削除処理
            Item::deleteItem($id);

            $message = '削除しました.';
            return response()->json(
                ['message' => $message],
                201,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }else{
            abort(404);
        }

    }


    /**
     * アイテム名検索
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $params = $request->all();
        $keyword = $params['keyword'];

        //キーワードがあれば検索する。なければメッセージのみを返す
        if(!is_null($keyword)){
            $itemCount = Item::findByKeywordItem($keyword)->count();

            $items = Item::findByKeywordItem($keyword);

            //ヒットした件数が1件以上であれば、アイテム情報を返す。なければメッセージのみを返す
            if($itemCount != 0){

                return response()->json(
                    ['items' => $items, 'itemCount' => $itemCount],
                    200,
                    [],
                    JSON_UNESCAPED_UNICODE
                );

            }else{

                $messages = "キーワードに当てはまるアイテムがありませんでした。";

                return response()->json(
                    ['messages' => $messages],
                    200,
                    [],
                    JSON_UNESCAPED_UNICODE
                );
            }
        }else{
            $messages = "キーワードに当てはまるアイテムがありませんでした。";

            return response()->json(
                ['messages' => $messages],
                200,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }


    }

    /**
     * 画像の登録
     * 画像ファイルをアップロードし、ファイルのimageディレクトリ以下のファイルパスを返す。
     * 保存するファイル形式は.png
     * ①ストレージをpublicに設定
     * ②confファイルから、保存先パスを取得
     * ③ファイル名を作成
     * ④base64をバイナリデータにエンコード
     * ⑤ファイルをアップロードする
     * @param $image
     * @return string
     */
    public function imageUpload($image)
    {
        //保存先の指定処理
        $disk = Storage::disk('public');
        $store_dir = config('filesystems.image');

        //保存データの準備
        $store_filename = date("Y_m_d_H_i_s"). '_image.png';
        $storefile = sprintf('%s/%s',$store_dir  ,$store_filename );
        $image = str_replace('data:image/png;base64,', '', $image);
        $contents = base64_decode($image);

        //保存データのアップロード
        $disk->put($storefile, $contents);

        return $storefile;
    }

    /**
     * 画像の削除
     * 画像ファイルを削除する。
     * @param $fileName
     */
    public function imageDelete($fileName)
    {
        $disk = Storage::disk('public');
        $disk->delete($fileName);
    }
}
