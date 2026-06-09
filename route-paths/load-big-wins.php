<?php
$resArr['data'] = [];

function maskWinnerName($name)
{
  $clean = trim((string) $name);
  if ($clean === "") {
    return "Player";
  }

  $clean = preg_replace('/\s+/', '_', $clean);
  $len = strlen($clean);

  if ($len <= 3) {
    return $clean . "***";
  }

  return substr($clean, 0, min(5, $len)) . "***" . substr($clean, -1);
}

function formatWinAmount($amount)
{
  $value = (float) $amount;

  if ($value >= 100000) {
    $short = $value / 100000;
    return rtrim(rtrim(number_format($short, 1), '0'), '.') . "L";
  }

  return number_format($value, 0);
}

function pickAvatar($index)
{
  $avatars = ["🧑", "👩", "👨", "🧔"];
  return $avatars[$index % count($avatars)];
}

$limit = isset($_GET['LIMIT']) ? (int) $_GET['LIMIT'] : 10;
if ($limit < 1) $limit = 10;
if ($limit > 30) $limit = 30;

$scope = strtolower(mysqli_real_escape_string($conn, $_GET['SCOPE'] ?? 'all'));

$where = "WHERE CAST(m.tbl_match_profit AS DECIMAL(18,2)) > 0";

if ($scope === "casino") {
  $where .= " AND LOWER(REPLACE(COALESCE(m.tbl_project_name, ''), ' ', '')) NOT IN ('sabasports','lucksport','lucksportgaming','lucksports','9wickets','esports')";
}

$todayWhere = "WHERE CAST(tbl_winning_amount AS DECIMAL(18,2)) > 0";
$todaySql = "
  SELECT
    tbl_mobile_num,
    tbl_avatar_id,
    tbl_winning_amount,
    tbl_time_stamp
  FROM tbltodaywinners
  {$todayWhere}
  ORDER BY id DESC
  LIMIT {$limit}
";

$todayQuery = mysqli_query($conn, $todaySql);

if ($todayQuery && mysqli_num_rows($todayQuery) > 0) {
  $indexNum = 0;

  while ($row = mysqli_fetch_assoc($todayQuery)) {
    $mobile = (string) ($row['tbl_mobile_num'] ?? '');
    $maskedMobile = strlen($mobile) >= 4 ? "Player***" . substr($mobile, -4) : "Player";
    $amount = (float) ($row['tbl_winning_amount'] ?? 0);

    $item = [];
    $item['avatar'] = pickAvatar($indexNum);
    $item['user'] = $maskedMobile;
    $item['game'] = $scope === "casino" ? "Casino Win" : "Live Game";
    $item['amount'] = formatWinAmount($amount);
    $item['amount_raw'] = number_format($amount, 2, '.', '');
    $item['time_stamp'] = $row['tbl_time_stamp'];

    array_push($resArr['data'], $item);
    $indexNum++;
  }

  $resArr['status_code'] = "success";
  mysqli_close($conn);
  echo json_encode($resArr);
  return;
}

$sql = "
  SELECT
    m.tbl_user_id,
    m.tbl_project_name,
    m.tbl_match_profit,
    m.tbl_time_stamp,
    m.tbl_updated_at,
    u.tbl_user_name,
    u.tbl_full_name
  FROM tblmatchplayed m
  LEFT JOIN tblusersdata u ON m.tbl_user_id = u.tbl_uniq_id
  {$where}
  ORDER BY COALESCE(m.tbl_updated_at, m.tbl_time_stamp) DESC, m.id DESC
  LIMIT {$limit}
";

$query = mysqli_query($conn, $sql);

if ($query) {
  $indexNum = 0;

  while ($row = mysqli_fetch_assoc($query)) {
    $rawName = $row['tbl_user_name'] ?: ($row['tbl_full_name'] ?: $row['tbl_user_id']);
    $gameName = trim((string) ($row['tbl_project_name'] ?? 'Casino Game'));
    if ($gameName === "") {
      $gameName = "Casino Game";
    }

    $amount = (float) ($row['tbl_match_profit'] ?? 0);

    $item = [];
    $item['avatar'] = pickAvatar($indexNum);
    $item['user'] = maskWinnerName($rawName);
    $item['game'] = $gameName;
    $item['amount'] = formatWinAmount($amount);
    $item['amount_raw'] = number_format($amount, 2, '.', '');
    $item['time_stamp'] = $row['tbl_updated_at'] ?: $row['tbl_time_stamp'];

    array_push($resArr['data'], $item);
    $indexNum++;
  }

  $resArr['status_code'] = count($resArr['data']) > 0 ? "success" : "no-records-found";
} else {
  $resArr['status_code'] = "query_error";
  $resArr['message'] = mysqli_error($conn);
}

mysqli_close($conn);
echo json_encode($resArr);
?>
