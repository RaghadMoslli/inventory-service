package com.shop.inventory.grpc;

import com.shop.inventory.service.InventoryService;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private final InventoryService inventoryService;

    public InventoryGrpcService(InventoryService inventoryService) {
        this.inventoryService = inventoryService;
    }

    @Override
    public void getStock(GetStockRequest request, StreamObserver<GetStockResponse> responseObserver) {
        GetStockResponse.Builder responseBuilder = GetStockResponse.newBuilder();
        for (long productId : request.getProductIdsList()) {
            int available = inventoryService.getStock(productId);
            responseBuilder.addItems(StockItem.newBuilder().setProductId(productId).setAvailable(available).build());
        }
        responseObserver.onNext(responseBuilder.build());
        responseObserver.onCompleted();
    }

    @Override
    public void reserve(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        // تحويل طلبات gRPC إلى الصيغة التي تفهمها الـ Service
        var items = request.getItemsList().stream()
                .map(i -> new InventoryService.ItemRequest(i.getProductId(), i.getQuantity()))
                .toList();

        InventoryService.ReservationResult result = inventoryService.reserveStock(request.getRequestId(), items);

        ReserveResponse.Builder responseBuilder = ReserveResponse.newBuilder()
                .setSuccess(result.success());

        if (result.success()) {
            responseBuilder.setReservationId(result.reservationId());
        } else {
            for (var shortage : result.shortages()) {
                responseBuilder.addShortages(Shortage.newBuilder()
                        .setProductId(shortage.productId())
                        .setRequested(shortage.requested())
                        .setAvailable(shortage.available())
                        .build());
            }
        }

        responseObserver.onNext(responseBuilder.build());
        responseObserver.onCompleted();
    }

    @Override
    public void release(ReleaseRequest request, StreamObserver<ReleaseResponse> responseObserver) {
        boolean released = inventoryService.releaseStock(request.getReservationId());
        ReleaseResponse response = ReleaseResponse.newBuilder().setReleased(released).build();
        responseObserver.onNext(response);
        responseObserver.onCompleted();
    }

    @Override
    public void setStock(SetStockRequest request, StreamObserver<SetStockResponse> responseObserver) {
        inventoryService.setStock(request.getProductId(), request.getQuantity());
        SetStockResponse response = SetStockResponse.newBuilder().setOk(true).build();
        responseObserver.onNext(response);
        responseObserver.onCompleted();
    }
}